<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One grading session. It keeps a copy of the rubric, so editing or deleting
 * the rubric later never changes grades already given.
 */
class GradingBatch extends Model
{
    /** A browser that vanished mid-request leaves its item stuck this long. */
    private const STALE_MINUTES = 5;

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

    public function releaseStaleItems(): int
    {
        return $this->items()
            ->where('status', GradingItem::PROCESSING)
            ->where('updated_at', '<', now()->subMinutes(self::STALE_MINUTES))
            ->update(['status' => GradingItem::QUEUED]);
    }
}
