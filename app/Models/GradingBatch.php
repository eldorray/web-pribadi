<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One grading session. It keeps a copy of the rubric, so editing or deleting
 * the rubric later never changes grades already given.
 */
class GradingBatch extends Model
{
    public const MAX_ITEMS = 100;

    /**
     * A browser that vanished mid-request leaves its item stuck this long. Must
     * outlast the slowest real grading (four 120-second calls with retries), or
     * a second tab could claim it again and pay the provider twice.
     */
    private const STALE_MINUTES = 10;

    protected $fillable = ['chat_user_id', 'name', 'rubric_snapshot', 'model'];

    protected function casts(): array
    {
        return ['rubric_snapshot' => 'array'];
    }

    /** @return BelongsTo<ChatUser, $this> */
    public function chatUser(): BelongsTo
    {
        return $this->belongsTo(ChatUser::class);
    }

    /** @return HasMany<GradingItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(GradingItem::class);
    }

    /** @return list<array{name: string, description: string, max_score: int|float}> */
    public function criteria(): array
    {
        return $this->rubric_snapshot['criteria'] ?? [];
    }

    public function maxScore(): float
    {
        return (float) array_sum(array_column($this->criteria(), 'max_score'));
    }

    /** What the AI's proposal is out of: criteria left to the lecturer don't count. */
    public function aiMaxScore(): float
    {
        return (float) array_sum(array_map(
            fn (array $criterion) => ($criterion['human_only'] ?? false) ? 0 : $criterion['max_score'],
            $this->criteria(),
        ));
    }

    /** Two files with the same name are two submissions, so neither may vanish. */
    public function uniqueStudentName(string $base): string
    {
        $base = Str::limit(trim($base) ?: 'Tanpa nama', 200, '');
        $name = $base;
        $copy = 1;

        while ($this->items()->where('student_name', $name)->exists()) {
            $copy++;
            $name = "{$base} ({$copy})";
        }

        return $name;
    }

    public function releaseStaleItems(): int
    {
        return $this->items()
            ->where('status', GradingItem::PROCESSING)
            ->where('updated_at', '<', now()->subMinutes(self::STALE_MINUTES))
            ->update(['status' => GradingItem::QUEUED]);
    }
}
