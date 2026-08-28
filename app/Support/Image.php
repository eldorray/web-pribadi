<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Downscale + re-encode uploaded raster images to WebP on the public disk.
 *
 * Uploads used to be stored byte-for-byte as they arrived, which meant a 1.2 MB
 * camera PNG was being served into a 48px avatar tile. Everything that lands in
 * public storage now goes through here first.
 *
 * ponytail: plain GD, no Intervention/Imagick wrapper — resize + one encode is
 * all this needs. Revisit if the site ever wants multiple widths per upload.
 */
final class Image
{
    /** WebP quality. 82 is visually lossless for photos at these sizes. */
    private const QUALITY = 82;

    /**
     * Optimize a file already stored on the 'public' disk.
     *
     * @param  string  $path  disk-relative path, e.g. "settings/abc.png"
     * @param  int  $maxWidth  longest edge to keep; larger images are downscaled
     * @return string the resulting disk-relative path — a ".webp" sibling on
     *                success, or $path untouched if the file can't be processed
     *                (SVG, corrupt bytes, GD missing)
     */
    public static function optimize(string $path, int $maxWidth = 1024): string
    {
        $disk = Storage::disk('public');

        if (! extension_loaded('gd') || ! $disk->exists($path)) {
            return $path;
        }

        // SVG is already small and vector; GD can't read it anyway.
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'svg') {
            return $path;
        }

        try {
            $source = @imagecreatefromstring($disk->get($path) ?? '');

            if ($source === false) {
                return $path;
            }

            $resized = self::downscale($source, $maxWidth);

            $webpPath = preg_replace('/\.[^.\/]+$/', '', $path).'.webp';

            $buffer = self::encodeWebp($resized);

            imagedestroy($resized);
            if ($resized !== $source) {
                imagedestroy($source);
            }

            if ($buffer === null) {
                return $path;
            }

            $disk->put($webpPath, $buffer);

            if ($webpPath !== $path) {
                $disk->delete($path);
            }

            return $webpPath;
        } catch (\Throwable $e) {
            Log::warning('Image::optimize failed, keeping original', [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);

            return $path;
        }
    }

    /**
     * @return \GdImage the same handle when no resize was needed
     */
    private static function downscale(\GdImage $source, int $maxWidth): \GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $longest = max($width, $height);

        if ($longest <= $maxWidth) {
            return $source;
        }

        $scale = $maxWidth / $longest;
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $target = imagecreatetruecolor($newWidth, $newHeight);

        // Keep transparency instead of compositing onto black.
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));

        imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $target;
    }

    private static function encodeWebp(\GdImage $image): ?string
    {
        if (! function_exists('imagewebp')) {
            return null;
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        $ok = imagewebp($image, null, self::QUALITY);
        $buffer = (string) ob_get_clean();

        return ($ok && $buffer !== '') ? $buffer : null;
    }
}
