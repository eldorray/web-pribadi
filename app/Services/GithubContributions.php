<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Fetches GitHub contribution data via the public
 * github-contributions-api.jogruber.de endpoint (no auth required).
 *
 * Cached for 6 hours to avoid hammering the API.
 *
 * Returns:
 *   [
 *     'total'         => int,    // contributions in last year
 *     'weeks'         => array,  // array of weeks (53), each week = array of 7 days
 *     'months'        => array,  // [['label' => 'May', 'col' => 0], ...]
 *     'username'      => string,
 *   ]
 *
 * Each day:
 *   ['date' => 'YYYY-MM-DD', 'count' => int, 'level' => 0-4, 'empty' => bool]
 */
class GithubContributions
{
    public static function fetch(string $username): ?array
    {
        if (empty($username)) {
            return null;
        }

        $cacheKey = "github_contrib::{$username}";

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($username) {
            try {
                $response = Http::timeout(6)
                    ->get("https://github-contributions-api.jogruber.de/v4/{$username}", [
                        'y' => 'last',
                    ]);

                if (! $response->successful()) {
                    return null;
                }

                $data = $response->json();
                $contributions = $data['contributions'] ?? [];

                if (empty($contributions)) {
                    return null;
                }

                // Total contributions in last year
                $total = $data['total']['lastYear']
                    ?? array_sum(array_column($contributions, 'count'));

                // Pad the start so the grid aligns to Sunday (day 0 = Sun)
                $firstDate = new \DateTime($contributions[0]['date']);
                $firstDow = (int) $firstDate->format('w'); // 0=Sun..6=Sat

                $padded = [];
                for ($i = 0; $i < $firstDow; $i++) {
                    $padded[] = ['empty' => true];
                }
                foreach ($contributions as $c) {
                    $padded[] = [
                        'empty' => false,
                        'date'  => $c['date'],
                        'count' => $c['count'],
                        'level' => $c['level'] ?? 0,
                    ];
                }

                // Chunk into weeks of 7
                $weeks = array_chunk($padded, 7);

                // Compute month labels with column index (a month label is shown at the
                // first week-column whose first non-empty day is the 1st-7th of a month)
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
                        break; // only check first non-empty day per column
                    }
                }

                return [
                    'username' => $username,
                    'total'    => $total,
                    'weeks'    => $weeks,
                    'months'   => $months,
                ];
            } catch (\Throwable $e) {
                return null;
            }
        });
    }
}
