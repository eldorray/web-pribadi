<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches GitHub contribution data via the public
 * github-contributions-api.jogruber.de endpoint (no auth required).
 *
 * Cached for 6 hours to avoid hammering the API.
 *
 * Returns: [ 'total', 'weeks', 'months', 'username' ]  or null on failure.
 *
 * Each day:
 *   ['date' => 'YYYY-MM-DD', 'count' => int, 'level' => 0-4, 'empty' => bool]
 */
class GithubContributions
{
    public static function fetch(string $username): ?array
    {
        if (empty($username)) {
            Log::debug('GithubContributions: empty username');

            return null;
        }

        $cacheKey = "github_contrib::{$username}";

        // Cache::remember() treats a null result as a cache miss, so a failing
        // API meant 3 HTTP attempts (up to ~25s) on every single page load.
        // Store `false` as a negative-result sentinel with a shorter TTL.
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached ?: null;
        }

        $data = self::fetchFresh($username);
        Cache::put($cacheKey, $data ?? false, $data ? now()->addHours(6) : now()->addMinutes(10));

        return $data;
    }

    /**
     * Bypass cache. Useful for diagnostics.
     */
    public static function fetchFresh(string $username): ?array
    {
        try {
            $response = Http::timeout(8)
                ->withOptions([
                    'verify' => true,         // verify SSL
                    'connect_timeout' => 5,
                ])
                ->retry(2, 200)
                ->get('https://github-contributions-api.jogruber.de/v4/'.rawurlencode($username), [
                    'y' => 'last',
                ]);

            if (! $response->successful()) {
                Log::warning('GithubContributions: HTTP not successful', [
                    'username' => $username,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 200),
                ]);

                return null;
            }

            $data = $response->json();
            $contributions = $data['contributions'] ?? [];

            if (empty($contributions)) {
                Log::warning('GithubContributions: empty contributions array', [
                    'username' => $username,
                    'keys' => array_keys($data ?? []),
                ]);

                return null;
            }

            $total = $data['total']['lastYear']
                ?? array_sum(array_column($contributions, 'count'));

            // Pad start so the grid aligns to Sunday (day 0 = Sun)
            $firstDate = new \DateTime($contributions[0]['date']);
            $firstDow = (int) $firstDate->format('w');

            $padded = [];
            for ($i = 0; $i < $firstDow; $i++) {
                $padded[] = ['empty' => true];
            }
            foreach ($contributions as $c) {
                $padded[] = [
                    'empty' => false,
                    'date' => $c['date'],
                    'count' => $c['count'],
                    'level' => $c['level'] ?? 0,
                ];
            }

            $weeks = array_chunk($padded, 7);

            // Month labels with column index
            $months = [];
            $lastMonth = null;
            foreach ($weeks as $colIndex => $week) {
                foreach ($week as $day) {
                    if (! empty($day['empty'])) {
                        continue;
                    }
                    $d = new \DateTime($day['date']);
                    $dayNum = (int) $d->format('j');
                    $monthShort = $d->format('M');
                    if ($monthShort !== $lastMonth && $dayNum <= 7) {
                        $months[] = ['label' => $monthShort, 'col' => $colIndex];
                        $lastMonth = $monthShort;
                    }
                    break;
                }
            }

            return [
                'username' => $username,
                'total' => $total,
                'weeks' => $weeks,
                'months' => $months,
            ];
        } catch (\Throwable $e) {
            Log::error('GithubContributions: exception', [
                'username' => $username,
                'message' => $e->getMessage(),
                'class' => get_class($e),
            ]);

            return null;
        }
    }

    /**
     * Diagnostic helper — returns details on what failed.
     */
    public static function diagnose(string $username): array
    {
        $report = [
            'username' => $username,
            'username_empty' => empty($username),
            'cache_has_entry' => Cache::has("github_contrib::{$username}"),
            'cache_value' => null,
            'http_test' => null,
            'http_error' => null,
        ];

        try {
            $cached = Cache::get("github_contrib::{$username}");
            $report['cache_value'] = is_null($cached) ? 'null (failed fetch was cached)' : 'has data';
        } catch (\Throwable $e) {
            $report['cache_value'] = 'cache error: '.$e->getMessage();
        }

        // Try a live request bypassing cache
        try {
            $resp = Http::timeout(8)->get('https://github-contributions-api.jogruber.de/v4/'.rawurlencode($username), ['y' => 'last']);
            $report['http_test'] = [
                'status' => $resp->status(),
                'successful' => $resp->successful(),
                'body_length' => strlen($resp->body()),
                'body_sample' => mb_substr($resp->body(), 0, 120),
            ];
        } catch (\Throwable $e) {
            $report['http_error'] = $e->getMessage();
        }

        return $report;
    }
}
