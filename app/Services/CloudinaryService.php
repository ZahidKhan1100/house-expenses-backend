<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudinaryService
{
    public function deleteImageByPublicId(string $publicId): void
    {
        $cloudName = env('CLOUDINARY_CLOUD_NAME');
        $apiKey = env('CLOUDINARY_API_KEY');
        $apiSecret = env('CLOUDINARY_API_SECRET');

        if (!$cloudName || !$apiKey || !$apiSecret) {
            return;
        }

        $publicId = trim($publicId);
        if ($publicId === '') {
            return;
        }

        $timestamp = time();
        $signatureBase = 'public_id=' . $publicId . '&timestamp=' . $timestamp;
        $signature = sha1($signatureBase . $apiSecret);

        $response = Http::timeout(8)->asForm()->post(
            "https://api.cloudinary.com/v1_1/{$cloudName}/image/destroy",
            [
                'public_id' => $publicId,
                'timestamp' => $timestamp,
                'api_key' => $apiKey,
                'signature' => $signature,
            ],
        );

        if (! $response->successful()) {
            Log::warning('Cloudinary image destroy failed', [
                'public_id_prefix' => substr($publicId, 0, 80),
                'status' => $response->status(),
            ]);
        }
    }
}

