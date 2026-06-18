<?php

namespace Tests\Feature;

use App\Models\Visitor;
use App\Services\VisitorTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VisitorCounterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_tracks_a_visitor_once_per_session(): void
    {
        $this->get('/')->assertOk();
        $this->get('/about')->assertOk();

        $this->assertSame(1, Visitor::count());
    }

    #[Test]
    public function it_returns_aggregated_stats_with_flags(): void
    {
        Visitor::create([
            'country_code' => 'ID',
            'ip_hash' => hash('sha256', 'visitor-1'),
            'path' => '/',
            'visited_at' => now(),
        ]);

        Visitor::create([
            'country_code' => 'US',
            'ip_hash' => hash('sha256', 'visitor-2'),
            'path' => '/about',
            'visited_at' => now(),
        ]);

        Visitor::create([
            'country_code' => 'ID',
            'ip_hash' => hash('sha256', 'visitor-3'),
            'path' => '/contact',
            'visited_at' => now(),
        ]);

        Cache::forget('visitor_stats');

        $stats = VisitorTracker::stats();

        $this->assertSame(3, $stats['total']);
        $this->assertSame('ID', $stats['countries'][0]['code']);
        $this->assertSame('🇮🇩', $stats['countries'][0]['flag']);
        $this->assertSame(2, $stats['countries'][0]['count']);
    }

    #[Test]
    public function it_shows_visitor_counter_on_first_visit(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('visitor-counter', false)
            ->assertSee('visitor', false);

        $this->assertSame(1, Visitor::count());
    }

    #[Test]
    public function it_shows_visitor_counter_on_public_pages(): void
    {
        Visitor::create([
            'country_code' => 'ID',
            'ip_hash' => hash('sha256', 'visitor-1'),
            'path' => '/',
            'visited_at' => now(),
        ]);

        Cache::forget('visitor_stats');

        $this->get('/')
            ->assertOk()
            ->assertSee('visitor-counter', false)
            ->assertSee('🇮🇩', false);
    }
}
