<?php
namespace App\Helpers;

/**
 * Resizes and re-compresses an image in place so nothing served to a
 * visitor's browser is larger than a real web image needs to be. Package
 * gallery uploads were reaching 9MB+ straight from a phone camera with no
 * processing at all — this is the single choke point both upload endpoints
 * (gallery, team/fleet/accommodation) now route through.
 */
class ImageOptimizer
{
    /** Longest edge, in pixels, any uploaded image is allowed to keep. */
    private const MAX_DIMENSION = 1920;

    /** JPEG/WebP quality — visually lossless at web viewing sizes. */
    private const QUALITY = 82;

    /**
     * Re-encode $path in place, downscaling if either dimension exceeds
     * MAX_DIMENSION. No-op (returns true) if the file is already within
     * bounds and doesn't need recompression. Never throws — a failure here
     * should never block the upload the caller already accepted.
     */
    public static function optimize(string $path, string $mime): bool
    {
        try {
            $info = @getimagesize($path);
            if (!$info) return false;
            [$width, $height] = $info;

            $src = match ($mime) {
                'image/jpeg' => @imagecreatefromjpeg($path),
                'image/png'  => @imagecreatefrompng($path),
                'image/webp' => @imagecreatefromwebp($path),
                default      => null,
            };
            if (!$src) return false;

            $scale = min(1, self::MAX_DIMENSION / max($width, $height));
            $needsResize = $scale < 1;

            if ($needsResize) {
                $newW = (int)round($width * $scale);
                $newH = (int)round($height * $scale);
                $dst  = imagecreatetruecolor($newW, $newH);
                if ($mime === 'image/png' || $mime === 'image/webp') {
                    imagealphablending($dst, false);
                    imagesavealpha($dst, true);
                }
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);
                imagedestroy($src);
                $src = $dst;
            }

            $ok = match ($mime) {
                'image/jpeg' => imagejpeg($src, $path, self::QUALITY),
                'image/png'  => imagepng($src, $path, 6),
                'image/webp' => imagewebp($src, $path, self::QUALITY),
                default      => false,
            };
            imagedestroy($src);
            return $ok;
        } catch (\Throwable $e) {
            error_log('[ImageOptimizer] ' . $e->getMessage());
            return false;
        }
    }
}
