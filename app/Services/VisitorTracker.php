<?php

namespace App\Services;

use App\Models\Visitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VisitorTracker
{
    private const CACHE_KEY = 'visitor_stats';

    private const CACHE_TTL_SECONDS = 300;

    public static function track(Request $request): void
    {
        if (self::shouldSkip($request)) {
            return;
        }

        $countryCode = self::resolveCountry($request);
        $ipHash = self::hashIp($request->ip() ?? '');

        Visitor::create([
            'country_code' => $countryCode,
            'ip_hash' => $ipHash,
            'path' => $request->path(),
            'visited_at' => now(),
        ]);

        $request->session()->put('visitor_tracked', true);

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{total: int, countries: list<array{code: string, flag: string, count: int}>}
     */
    public static function stats(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            $total = Visitor::count();

            $countries = Visitor::query()
                ->selectRaw('country_code, COUNT(*) as count')
                ->groupBy('country_code')
                ->orderByDesc('count')
                ->limit(12)
                ->get()
                ->map(fn ($row) => [
                    'code' => $row->country_code,
                    'flag' => self::countryFlag($row->country_code),
                    'count' => (int) $row->count,
                ])
                ->values()
                ->all();

            return [
                'total' => $total,
                'countries' => $countries,
            ];
        });
    }

    public static function countryFlag(string $code): string
    {
        $code = strtoupper(trim($code));

        if ($code === '' || $code === 'XX' || strlen($code) !== 2) {
            return '🌐';
        }

        if (! ctype_alpha($code)) {
            return '🌐';
        }

        return mb_chr(0x1F1E6 + ord($code[0]) - 65)
            .mb_chr(0x1F1E6 + ord($code[1]) - 65);
    }

    private static function shouldSkip(Request $request): bool
    {
        if ($request->session()->get('visitor_tracked')) {
            return true;
        }

        $userAgent = strtolower($request->userAgent() ?? '');

        if ($userAgent === '') {
            return true;
        }

        $botPatterns = [
            'bot', 'crawl', 'spider', 'slurp', 'mediapartners',
            'facebookexternalhit', 'whatsapp', 'telegrambot',
            'headless', 'lighthouse', 'pingdom', 'uptimerobot',
        ];

        foreach ($botPatterns as $pattern) {
            if (str_contains($userAgent, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private static function resolveCountry(Request $request): string
    {
        $cfCountry = $request->header('CF-IPCountry');
        if (is_string($cfCountry) && strlen($cfCountry) === 2 && $cfCountry !== 'XX') {
            return strtoupper($cfCountry);
        }

        $ip = $request->ip();
        if (empty($ip) || self::isPrivateIp($ip)) {
            return 'XX';
        }

        $ipHash = self::hashIp($ip);
        $cacheKey = "visitor_country::{$ipHash}";

        return Cache::remember($cacheKey, now()->addDay(), function () use ($ip) {
            return self::lookupCountry($ip);
        });
    }

    private static function lookupCountry(string $ip): string
    {
        try {
            $response = Http::timeout(4)
                ->retry(1, 200)
                ->get("http://ip-api.com/json/{$ip}", [
                    'fields' => 'status,countryCode',
                ]);

            if (! $response->successful()) {
                return 'XX';
            }

            $data = $response->json();

            if (($data['status'] ?? '') !== 'success') {
                return 'XX';
            }

            $code = $data['countryCode'] ?? 'XX';

            return is_string($code) && strlen($code) === 2
                ? strtoupper($code)
                : 'XX';
        } catch (\Throwable $e) {
            Log::debug('VisitorTracker: geo lookup failed', [
                'message' => $e->getMessage(),
            ]);

            return 'XX';
        }
    }

    private static function hashIp(string $ip): string
    {
        return hash('sha256', $ip.config('app.key'));
    }

    private static function isPrivateIp(string $ip): bool
    {
        return ! filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
}
