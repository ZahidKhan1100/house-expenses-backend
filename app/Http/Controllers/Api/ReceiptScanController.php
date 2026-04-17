<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ReceiptImagePreparer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReceiptScanController extends Controller
{
    public function extract(Request $request)
    {
        $user = $request->user();
        if (!$user?->house_id) {
            return response()->json(['message' => 'No house'], 400);
        }

        $data = $request->validate([
            'image' => ['required_without:image_url', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'], // 10MB
            'image_url' => ['required_without:image', 'url', 'max:2048'],
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

        $prompt = 'Act as an expert accountant. Analyze this receipt image and return a JSON object with: total_amount (float), currency (ISO string), merchant_name (string), and date (YYYY-MM-DD). If you cannot find a value, return null for that field.';

        $payload = [
            'contents' => [[
                'role' => 'user',
                'parts' => [
                    ['text' => $prompt . "\n\nReturn ONLY valid JSON. No markdown, no backticks."],
                    ['inline_data' => ['mime_type' => $mime, 'data' => $base64]],
                ],
            ]],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 256,
                'responseMimeType' => 'application/json',
            ],
        ];

        try {
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';
            $resp = Http::timeout(25)
                ->post($url . '?key=' . urlencode($apiKey), $payload);

            if (!$resp->successful()) {
                Log::warning('Gemini receipt extract HTTP error', [
                    'status' => $resp->status(),
                    'body' => $resp->body(),
                ]);
                return response()->json(['message' => 'Receipt scan failed'], 502);
            }

            $json = $resp->json();
            $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if (!is_string($text) || trim($text) === '') {
                return response()->json(['message' => 'Receipt scan returned no content'], 502);
            }

            // Parse model output as JSON
            $parsed = json_decode($text, true);
            if (!is_array($parsed)) {
                return response()->json([
                    'message' => 'Could not parse receipt scan result',
                    'raw' => $text,
                ], 422);
            }

            $total = array_key_exists('total_amount', $parsed) ? $parsed['total_amount'] : null;
            $currency = array_key_exists('currency', $parsed) ? $parsed['currency'] : null;
            $merchant = array_key_exists('merchant_name', $parsed) ? $parsed['merchant_name'] : null;
            $date = array_key_exists('date', $parsed) ? $parsed['date'] : null;

            $out = [
                'total_amount' => is_numeric($total) ? (float) $total : null,
                'currency' => is_string($currency) && $currency !== '' ? strtoupper(trim($currency)) : null,
                'merchant_name' => is_string($merchant) && $merchant !== '' ? trim($merchant) : null,
                'date' => is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null,
            ];

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

