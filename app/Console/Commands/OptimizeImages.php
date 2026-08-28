<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\SiteSetting;
use App\Models\Tool;
use App\Support\Image;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * One-off (and safe to re-run) pass over images uploaded before
 * {@see Image::optimize} existed on the upload paths.
 *
 * Rewrites each referenced file to a downscaled WebP and points the owning DB
 * row at the new path. Files nothing references are left alone.
 */
class OptimizeImages extends Command
{
    protected $signature = 'images:optimize {--dry-run : Report what would change without touching anything}';

    protected $description = 'Downscale and re-encode referenced storage images to WebP';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $savedBytes = 0;
        $converted = 0;

        foreach ($this->targets() as $target) {
            [$model, $attribute, $maxWidth] = $target;

            $path = $this->diskPath((string) $model->{$attribute});

            if ($path === null || ! Storage::disk('public')->exists($path)) {
                continue;
            }

            $before = (int) Storage::disk('public')->size($path);

            if ($dryRun) {
                $this->line(sprintf('would optimize %s (%s) at max %dpx', $path, $this->human($before), $maxWidth));

                continue;
            }

            $newPath = Image::optimize($path, $maxWidth);

            if ($newPath === $path && Storage::disk('public')->size($path) === $before) {
                continue;
            }

            $after = (int) Storage::disk('public')->size($newPath);
            $savedBytes += $before - $after;
            $converted++;

            $model->{$attribute} = $this->rewriteReference((string) $model->{$attribute}, $newPath);
            $model->save();

            $this->line(sprintf('%s → %s  (%s → %s)', $path, $newPath, $this->human($before), $this->human($after)));
        }

        if ($dryRun) {
            return self::SUCCESS;
        }

        $this->info(sprintf('Optimized %d image(s), saved %s.', $converted, $this->human($savedBytes)));

        return self::SUCCESS;
    }

    /**
     * @return list<array{0: Model, 1: string, 2: int}>
     */
    private function targets(): array
    {
        $targets = [];

        foreach (SiteSetting::whereNotNull('value')->where('value', '!=', '')->get() as $setting) {
            if (! str_contains((string) $setting->value, '/storage/')) {
                continue;
            }

            $targets[] = [$setting, 'value', $setting->key === 'site_favicon' ? 128 : 512];
        }

        foreach (Project::whereNotNull('image')->where('image', '!=', '')->get() as $project) {
            $targets[] = [$project, 'image', 1024];
        }

        foreach (Tool::whereNotNull('icon_url')->where('icon_url', '!=', '')->get() as $tool) {
            $targets[] = [$tool, 'icon_url', 128];
        }

        return $targets;
    }

    /**
     * Reduce a stored reference to a path on the 'public' disk.
     *
     * References come in three shapes across the DB: a bare disk path
     * ("projects/x.png"), a root-relative URL ("/storage/settings/x.png") and a
     * legacy absolute URL. Anything off-disk (a remote http icon) returns null.
     */
    private function diskPath(string $reference): ?string
    {
        if ($reference === '') {
            return null;
        }

        if (str_contains($reference, '/storage/')) {
            return ltrim((string) substr($reference, strpos($reference, '/storage/') + strlen('/storage/')), '/');
        }

        if (str_starts_with($reference, 'http')) {
            return null;
        }

        return ltrim($reference, '/');
    }

    /** Put $newPath back in whatever shape the original reference used. */
    private function rewriteReference(string $reference, string $newPath): string
    {
        if (str_contains($reference, '/storage/')) {
            $prefix = substr($reference, 0, strpos($reference, '/storage/') + strlen('/storage/'));

            return $prefix.$newPath;
        }

        return $newPath;
    }

    private function human(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }

        return round($bytes / 1024).' KB';
    }
}
