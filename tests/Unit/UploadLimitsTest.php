<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\UploadLimits;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UploadLimitsTest extends TestCase
{
    /** @return array<string, array{string, int}> */
    public static function sizes(): array
    {
        return [
            'megabytes' => ['2M', 2097152],
            'kilobytes' => ['512K', 524288],
            'gigabytes' => ['1G', 1073741824],
            'bytes' => ['1024', 1024],
            'lowercase' => ['8m', 8388608],
            'unlimited' => ['0', 0],
        ];
    }

    #[Test]
    #[DataProvider('sizes')]
    public function parse_reads_ini_sizes(string $value, int $expected): void
    {
        $this->assertSame($expected, UploadLimits::parse($value));
    }

    #[Test]
    public function effective_takes_the_smallest(): void
    {
        $this->assertSame(2097152, UploadLimits::effective('2M', '8M', 10485760));
        $this->assertSame(8388608, UploadLimits::effective('64M', '8M', 10485760));
        $this->assertSame(10485760, UploadLimits::effective('64M', '64M', 10485760));
    }

    #[Test]
    public function zero_means_no_ini_limit(): void
    {
        $this->assertSame(10485760, UploadLimits::effective('0', '0', 10485760));
    }
}
