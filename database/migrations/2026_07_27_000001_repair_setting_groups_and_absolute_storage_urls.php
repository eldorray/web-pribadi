<?php

use App\Models\SiteSetting;
use App\Models\Tool;
use Illuminate\Database\Migrations\Migration;

/**
 * Repairs two bits of data damaged by bugs that are now fixed:
 *
 * 1. SiteSetting::set() used to overwrite `group` with its 'general' default,
 *    so saving the settings form collapsed every setting into one group.
 *    Restores the intended groups.
 *
 * 2. Uploaded images were stored as asset() absolute URLs (e.g.
 *    http://127.0.0.1:8000/storage/...), which break as soon as the site
 *    moves domain. Rewrites them to root-relative /storage/... paths.
 */
return new class extends Migration
{
    private const GROUPS = [
        'hero' => [
            'hero_label', 'hero_title_1', 'hero_title_2', 'hero_title_highlight',
            'hero_title_highlight_2', 'hero_title_highlight_3', 'hero_subtitle',
        ],
        'about' => [
            'about_label', 'about_title', 'about_text_1', 'about_text_2', 'about_portrait',
        ],
        'stats' => [
            'stat_1_value', 'stat_1_label', 'stat_2_value', 'stat_2_label',
            'stat_3_value', 'stat_3_label',
        ],
        'about_page' => [
            'about_page_portrait', 'about_page_intro', 'about_page_bio',
            'about_page_years', 'about_page_projects',
        ],
        'contact' => [
            'contact_address_1', 'contact_address_2', 'contact_address_3',
            'contact_email', 'contact_image', 'contact_availability',
        ],
        'general' => [
            'site_name', 'site_tagline', 'github_username', 'site_favicon',
        ],
    ];

    public function up(): void
    {
        foreach (self::GROUPS as $group => $keys) {
            SiteSetting::whereIn('key', $keys)->update(['group' => $group]);
        }

        foreach (SiteSetting::where('value', 'like', '%/storage/%')->get() as $setting) {
            if ($relative = $this->toRelative($setting->value)) {
                $setting->update(['value' => $relative]);
            }
        }

        foreach (Tool::where('icon_url', 'like', '%/storage/%')->get() as $tool) {
            if ($relative = $this->toRelative($tool->icon_url)) {
                $tool->update(['icon_url' => $relative]);
            }
        }
    }

    public function down(): void
    {
        // Data repair — nothing to roll back.
    }

    /**
     * http://any-host/storage/foo.png  ->  /storage/foo.png
     * Leaves values that are already relative, or not storage URLs, alone.
     */
    private function toRelative(?string $value): ?string
    {
        if (! $value || ! preg_match('#^https?://[^/]+(/storage/.*)$#', $value, $m)) {
            return null;
        }

        return $m[1];
    }
};
