<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Shrink uploads and Cloudinary URLs before sending bytes to Gemini (cost + latency).
 */
final class ReceiptImagePreparer
{
    public static function fromUploadedFile(UploadedFile $file, int $maxWidth): array
    {
        $raw = file_get_contents($file->getRealPath()) ?: '';
        $mime = $file->getMimeType() ?: 'image/jpeg';

        return self::shrinkBinary($raw, $mime, $maxWidth);
    }

    public static function fromRemoteUrl(string $url, int $maxWidth): array
    {
        $fetchUrl = CloudinaryUrl::withLimitWidth($url, $maxWidth);

        $resp = Http::timeout(20)
            ->withHeaders(['Accept' => 'image/*'])
            ->get($fetchUrl);

        if (! $resp->successful()) {
            Log::warning('Receipt image fetch failed', [
                'status' => $resp->status(),
                'url' => $fetchUrl,
            ]);
            throw new \RuntimeException('Could not download receipt image');
        }

        $body = $resp->body();
        $mime = $resp->header('Content-Type') ?? 'image/jpeg';
        if (! str_starts_with((string) $mime, 'image/')) {
            $mime = 'image/jpeg';
        }

        return self::shrinkBinary($body, $mime, $maxWidth);
    }

    /**
     * @return array{0: string, 1: string} binary, mime for Gemini inline_data
     */
    private static function shrinkBinary(string $binary, string $mime, int $maxWidth): array
    {
        if ($binary === '' || $maxWidth < 1) {
            return [$binary, $mime];
        }

        if (! extension_loaded('gd')) {
            return [$binary, $mime];
        }

        $img = @imagecreatefromstring($binary);
        if (! $img) {
            return [$binary, $mime];
        }

        $w = imagesx($img);
        $h = imagesy($img);
        if ($w <= 0 || $h <= 0) {
            imagedestroy($img);

            return [$binary, $mime];
        }

        if ($w <= $maxWidth) {
            imagedestroy($img);

            return [$binary, $mime];
        }

        $newH = (int) max(1, round($h * ($maxWidth / $w)));
        $scaled = imagescale($img, $maxWidth, $newH);
        imagedestroy($img);
        if (! $scaled) {
            return [$binary, $mime];
        }

        ob_start();
        imagejpeg($scaled, null, 85);
        $jpeg = (string) ob_get_clean();
        imagedestroy($scaled);

        if ($jpeg === '') {
            return [$binary, $mime];
        }

        return [$jpeg, 'image/jpeg'];
    }
}
