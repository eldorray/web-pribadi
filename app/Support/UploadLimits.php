<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The real upload ceiling on this host. Shared hosting often sits below what the
 * feature would like, so the page reads it instead of assuming a number.
 */
final class UploadLimits
{
    private const CAP = 10485760; // 10 MB

    public static function bytes(): int
    {
        return self::effective((string) ini_get('upload_max_filesize'), (string) ini_get('post_max_size'), self::CAP);
    }

    /** In PHP a limit of 0 means "no limit", so it never lowers the ceiling. */
    public static function effective(string $uploadMax, string $postMax, int $cap): int
    {
        return min(array_filter([self::parse($uploadMax), self::parse($postMax), $cap]));
    }

    public static function parse(string $value): int
    {
        $value = trim($value);
        $number = (int) $value;

        return match (strtoupper(substr($value, -1))) {
            'G' => $number * 1073741824,
            'M' => $number * 1048576,
            'K' => $number * 1024,
            default => $number,
        };
    }
}
