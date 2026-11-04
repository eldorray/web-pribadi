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

    /** @return list<array{name: string, description: string, max_score: int|float, min_score?: int|float, weight?: int|float, human_only?: bool}> */
    public function criteria(): array
    {
        return $this->rubric_snapshot['criteria'] ?? [];
    }

    /** What a criterion counts for in the total: its weight, or its own maximum. */
    public static function worth(array $criterion): float
    {
        return (float) ($criterion['weight'] ?? $criterion['max_score']);
    }

    public function maxScore(): float
    {
        return (float) array_sum(array_map(self::worth(...), $this->criteria()));
    }

    /**
     * Each score is scaled by score / maximum × worth, the same as the class
     * spreadsheets' SUM(I5*20/4, …). Without weights that is a plain sum.
     *
     * @param  array<int, int|float|null>  $scores
     */
    public function totalOf(array $scores): float
    {
        $total = 0.0;

        foreach ($this->criteria() as $i => $criterion) {
            $total += (float) ($scores[$i] ?? 0) * self::worth($criterion) / (float) $criterion['max_score'];
        }

        return round($total, 2);
    }

    /** What the AI's proposal is out of: criteria left to the lecturer don't count. */
    public function aiMaxScore(): float
    {
        return (float) array_sum(array_map(
            fn (array $criterion) => ($criterion['human_only'] ?? false) ? 0 : self::worth($criterion),
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
