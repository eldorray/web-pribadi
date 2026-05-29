<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;

/**
 * Ensures the new general-group settings exist in production.
 *
 * - github_username  → used by the home heatmap component
 * - site_favicon     → site-wide favicon
 *
 * Uses updateOrCreate so it never overwrites a value the user already set.
 * Safe to run on any environment.
 */
return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            ['key' => 'github_username', 'value' => '', 'type' => 'text',  'group' => 'general'],
            ['key' => 'site_favicon',    'value' => '', 'type' => 'image', 'group' => 'general'],
        ];

        foreach ($defaults as $row) {
            SiteSetting::firstOrCreate(
                ['key' => $row['key']],
                $row
            );
        }
    }

    public function down(): void
    {
        SiteSetting::whereIn('key', ['github_username', 'site_favicon'])->delete();
    }
};
