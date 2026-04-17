<?php

namespace App\Support;

/**
 * Cloudinary delivery URLs: insert on-the-fly transforms after /upload/
 * e.g. .../image/upload/w_1000,c_limit/v123/folder/receipt.jpg
 */
final class CloudinaryUrl
{
    public static function withLimitWidth(string $url, int $maxWidth = 1000): string
    {
        $url = trim($url);
        if ($url === '' || ! str_contains($url, 'res.cloudinary.com')) {
            return $url;
        }

        if (! preg_match('#/image/upload/#', $url)) {
            return $url;
        }

        // Already has a transformation chain (starts with something like w_ or c_)
        if (preg_match('#/image/upload/[^/]*w_\d+#', $url)) {
            return $url;
        }

        $transform = 'w_'.$maxWidth.',c_limit';

        return preg_replace('#(/image/upload/)#', '$1'.$transform.'/', $url, 1) ?? $url;
    }
}
