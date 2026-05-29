<?php

namespace App\Console\Commands;

use App\Models\SiteSetting;
use App\Services\GithubContributions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class GithubDiagnose extends Command
{
    protected $signature = 'github:diagnose {username? : Override username from settings}';

    protected $description = 'Diagnose why the GitHub contributions heatmap is not displaying';

    public function handle(): int
    {
        $username = $this->argument('username') ?: SiteSetting::get('github_username');

        $this->info('--- GitHub Contributions Diagnose ---');
        $this->line("Username from settings: '" . SiteSetting::get('github_username', '(empty)') . "'");
        $this->line("Username being tested:  '" . ($username ?: '(empty)') . "'");
        $this->newLine();

        if (empty($username)) {
            $this->error('FAIL: No github_username set. Visit /admin/settings → general → github_username.');
            return Command::FAILURE;
        }

        // Clear cache for fresh test
        Cache::forget("github_contrib::{$username}");

        $report = GithubContributions::diagnose($username);

        $this->line('Cache had stale entry: ' . ($report['cache_has_entry'] ? 'yes' : 'no (cleared above)'));

        if ($report['http_error']) {
            $this->error('HTTP ERROR: ' . $report['http_error']);
            $this->warn('Likely causes: outbound HTTP blocked by hosting, no curl/openssl, DNS resolution.');
            return Command::FAILURE;
        }

        $this->newLine();
        $this->line('--- HTTP Test ---');
        $http = $report['http_test'];
        $this->line("Status: {$http['status']}");
        $this->line("Successful: " . ($http['successful'] ? 'YES' : 'NO'));
        $this->line("Body length: {$http['body_length']} bytes");
        $this->line("Body sample: {$http['body_sample']}");

        if (! $http['successful']) {
            $this->error('FAIL: API responded with non-2xx status. Username may not exist on GitHub.');
            return Command::FAILURE;
        }

        // Try the full fetch
        $data = GithubContributions::fetchFresh($username);

        if (! $data) {
            $this->error('FAIL: Service returned null even though HTTP succeeded. Check storage/logs/laravel.log.');
            return Command::FAILURE;
        }

        $this->newLine();
        $this->info('SUCCESS: Heatmap should render.');
        $this->line("Total contributions: {$data['total']}");
        $this->line("Weeks: " . count($data['weeks']));
        $this->line("Months: " . count($data['months']));

        return Command::SUCCESS;
    }
}
