<?php

namespace Tests\Unit;

use App\Services\VisitorTracker;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VisitorTrackerTest extends TestCase
{
    #[Test]
    public function it_converts_country_codes_to_flag_emoji(): void
    {
        $this->assertSame('🇮🇩', VisitorTracker::countryFlag('ID'));
        $this->assertSame('🇺🇸', VisitorTracker::countryFlag('us'));
        $this->assertSame('🌐', VisitorTracker::countryFlag('XX'));
        $this->assertSame('🌐', VisitorTracker::countryFlag(''));
    }
}
