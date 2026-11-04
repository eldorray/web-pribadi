<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One student's submission. `ai_result` is only ever a proposal; `final_scores`
 * belongs to the lecturer and a grade is final only once `approved_at` is set.
 */
class GradingItem extends Model
{
    /** A video still being turned into frames and transcript in the browser. */
    public const PREPARING = 'preparing';

    public const QUEUED = 'queued';

    public const PROCESSING = 'processing';

    public const READY = 'ready';

    public const APPROVED = 'approved';

    public const FAILED = 'failed';

    public const TRANSCRIPT_PENDING = 'pending';

    public const TRANSCRIPT_DONE = 'done';

    public const TRANSCRIPT_FAILED = 'failed';

    /** The video has no audio track at all. */
    public const TRANSCRIPT_NO_AUDIO = 'no_audio';

    /** The lecturer chose to grade without a transcript. */
    public const TRANSCRIPT_SKIPPED = 'skipped';

    protected $fillable = [
        'grading_batch_id',
        'student_name',
        'type',
        'original_name',
        'status',
        'files',
        'transcript',
        'transcript_status',
        'duration_seconds',
        'extracted_text',
        'ai_result',
        'final_scores',
        'lecturer_note',
        'approved_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'files' => 'array',
            'ai_result' => 'array',
            'final_scores' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<GradingBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(GradingBatch::class, 'grading_batch_id');
    }

    public function needsReview(): bool
    {
        return in_array(true, array_column($this->ai_result['criteria'] ?? [], 'needs_review'), true);
    }

    public function injectionAttempt(): bool
    {
        return (bool) ($this->ai_result['injection_attempt'] ?? false);
    }

    /** 22.0 reads "22", 66.67 reads "66.67". */
    public static function formatScore(float $score): string
    {
        return rtrim(rtrim(number_format($score, 2, '.', ''), '0'), '.');
    }

    public function aiTotal(): ?float
    {
        $criteria = $this->ai_result['criteria'] ?? null;

        return is_array($criteria) ? $this->batch->totalOf(array_column($criteria, 'score')) : null;
    }

    public function finalTotal(): ?float
    {
        $scores = $this->final_scores;

        if (! is_array($scores) || $scores === [] || in_array(null, $scores, true)) {
            return null;
        }

        return $this->batch->totalOf($scores);
    }

    public function finalScaled(): ?float
    {
        $total = $this->finalTotal();
        $max = $this->batch->maxScore();

        return $total === null || $max <= 0 ? null : round($total / $max * 100, 2);
    }
}
