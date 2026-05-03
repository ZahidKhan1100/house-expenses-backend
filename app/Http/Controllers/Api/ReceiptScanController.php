<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CloudinaryService;
use App\Support\ReceiptImagePreparer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReceiptScanController extends Controller
{
    /**
     * @param  array<string, mixed>  $payload
     */
    private function geminiGenerateContent(string $apiKey, string $model, array $payload): \Illuminate\Http\Client\Response
    {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($model).':generateContent';

        return Http::timeout(45)
            ->post($url.'?key='.urlencode($apiKey), $payload);
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function extractGeminiOutputText(array $json): ?string
    {
        $blockReason = $json['promptFeedback']['blockReason'] ?? null;
        if (is_string($blockReason) && $blockReason !== '') {
            Log::warning('Gemini receipt: prompt blocked', ['blockReason' => $blockReason]);

            return null;
        }

        $candidates = $json['candidates'] ?? null;
        if (! is_array($candidates) || $candidates === []) {
            Log::warning('Gemini receipt: no candidates', ['keys' => array_keys($json)]);

            return null;
        }

        $first = $candidates[0] ?? null;
        if (! is_array($first)) {
            return null;
        }

        $finish = $first['finishReason'] ?? null;
        if (is_string($finish) && $finish !== '' && $finish !== 'STOP' && $finish !== 'MAX_TOKENS') {
            Log::warning('Gemini receipt: unexpected finish', ['finishReason' => $finish]);
        }

        $parts = $first['content']['parts'] ?? null;
        if (! is_array($parts) || $parts === []) {
            return null;
        }

        $text = $parts[0]['text'] ?? null;

        return is_string($text) ? $text : null;
    }

    private function decodeReceiptJsonFromModel(string $text): ?array
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return null;
        }

        // Model sometimes wraps JSON in markdown fences despite instructions.
        $trimmed = preg_replace('/^\s*```(?:json)?\s*/i', '', $trimmed) ?? $trimmed;
        $trimmed = preg_replace('/\s*```\s*$/', '', $trimmed) ?? $trimmed;
        $trimmed = trim($trimmed);

        $parsed = json_decode($trimmed, true);

        return is_array($parsed) ? $parsed : null;
    }

    public function extract(Request $request)
    {
        $user = $request->user();
        if (!$user?->house_id) {
            return response()->json(['message' => 'No house'], 400);
        }

        $data = $request->validate([
            'image' => ['required_without:image_url', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'], // 10MB
            'image_url' => ['required_without:image', 'url', 'max:2048'],
            'destroy_cloudinary' => ['sometimes', 'boolean'],
            'cloudinary_public_id' => ['nullable', 'string', 'max:512'],
        ]);

        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey) {
            return response()->json(['message' => 'Gemini not configured'], 500);
        }

        $maxW = (int) config('houseexpenses.receipt_scan.max_width', 1000);
        try {
            if (! empty($data['image_url'] ?? null)) {
                [$binary, $mime] = ReceiptImagePreparer::fromRemoteUrl((string) $data['image_url'], $maxW);
            } else {
                $file = $data['image'];
                [$binary, $mime] = ReceiptImagePreparer::fromUploadedFile($file, $maxW);
            }
        } catch (\Throwable $e) {
            Log::warning('Receipt image prepare failed: '.$e->getMessage());

            return response()->json(['message' => 'Invalid receipt image'], 422);
        }

        $base64 = base64_encode($binary);

        $prompt = 'Act as an expert accountant. Analyze this receipt image and return a JSON object with: total_amount (float), currency (ISO string), merchant_name (string), date (YYYY-MM-DD), and category_hint (string). '
            .'For category_hint, suggest ONE short label for the type of spend (English), e.g. Groceries, Dining, Rent, Utilities, Transport, Shopping, Health, Entertainment, Subscriptions, Home, Other. '
            .'Infer from merchant and line items (e.g. supermarket -> Groceries, restaurant -> Dining). If unclear, use Other. '
            .'If you cannot find a value for a field, return null for that field.';

        $payload = [
            'contents' => [[
                'role' => 'user',
                'parts' => [
                    ['inline_data' => ['mime_type' => $mime, 'data' => $base64]],
                    ['text' => $prompt."\n\nReturn ONLY valid JSON. No markdown, no backticks."],
                ],
            ]],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 512,
            ],
        ];

        try {
            $preferred = trim((string) (config('houseexpenses.receipt_scan.gemini_model') ?: 'gemini-2.5-flash-lite'));
            $modelsToTry = array_values(array_unique(array_filter([
                $preferred !== '' ? $preferred : null,
                'gemini-2.5-flash-lite',
                'gemini-2.5-flash',
                'gemini-2.0-flash',
                'gemini-1.5-flash-8b',
            ])));

            $resp = null;
            foreach ($modelsToTry as $model) {
                $resp = $this->geminiGenerateContent($apiKey, $model, $payload);
                if ($resp->successful()) {
                    break;
                }

                $errMsg = (string) ($resp->json('error.message') ?? '');
                $status = $resp->status();
                $lower = strtolower($errMsg);

                // Try next model: wrong/retired id (404), or quota on one model (429), or overload.
                $tryNextModel = $status === 404
                    || $status === 429
                    || $status === 503
                    || str_contains($lower, 'not found')
                    || str_contains($lower, 'not supported')
                    || str_contains($lower, 'is not found')
                    || str_contains($lower, 'quota')
                    || str_contains($lower, 'resource_exhausted')
                    || str_contains($lower, 'exceeded your current quota');

                Log::warning('Gemini receipt extract HTTP error', [
                    'model' => $model,
                    'status' => $status,
                    'body' => $resp->body(),
                ]);

                if (! $tryNextModel) {
                    break;
                }
            }

            if ($resp === null || ! $resp->successful()) {
                return response()->json(['message' => 'Receipt scan failed'], 502);
            }

            $json = $resp->json();
            if (! is_array($json)) {
                return response()->json(['message' => 'Receipt scan failed'], 502);
            }

            $text = $this->extractGeminiOutputText($json);
            if (! is_string($text) || trim($text) === '') {
                return response()->json(['message' => 'Receipt scan returned no content'], 502);
            }

            $parsed = $this->decodeReceiptJsonFromModel($text);
            if (! is_array($parsed)) {
                return response()->json([
                    'message' => 'Could not parse receipt scan result',
                    'raw' => $text,
                ], 422);
            }

            $total = array_key_exists('total_amount', $parsed) ? $parsed['total_amount'] : null;
            $currency = array_key_exists('currency', $parsed) ? $parsed['currency'] : null;
            $merchant = array_key_exists('merchant_name', $parsed) ? $parsed['merchant_name'] : null;
            $date = array_key_exists('date', $parsed) ? $parsed['date'] : null;
            $categoryHint = array_key_exists('category_hint', $parsed) ? $parsed['category_hint'] : null;

            $out = [
                'total_amount' => is_numeric($total) ? (float) $total : null,
                'currency' => is_string($currency) && $currency !== '' ? strtoupper(trim($currency)) : null,
                'merchant_name' => is_string($merchant) && $merchant !== '' ? trim($merchant) : null,
                'date' => is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null,
                'category_hint' => is_string($categoryHint) && trim($categoryHint) !== '' ? substr(trim($categoryHint), 0, 80) : null,
            ];

            if (($data['destroy_cloudinary'] ?? false) && isset($data['image_url'])) {
                $pid = trim((string) ($data['cloudinary_public_id'] ?? ''));
                if ($pid !== '' && str_contains((string) $data['image_url'], 'res.cloudinary.com')) {
                    try {
                        app(CloudinaryService::class)->deleteImageByPublicId($pid);
                    } catch (\Throwable) {
                        // Do not fail the scan if CDN unlink races.
                    }
                }
            }

            return response()->json([
                'success' => true,
                'extraction' => $out,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Gemini receipt extract exception: ' . $e->getMessage());
            return response()->json(['message' => 'Receipt scan failed'], 502);
        }
    }
}

