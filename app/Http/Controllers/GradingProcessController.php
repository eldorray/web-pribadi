<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AiModel;
use App\Models\ChatUser;
use App\Models\GradingItem;
use App\Services\AiException;
use App\Services\GradingAi;
use Illuminate\Http\JsonResponse;

/**
 * Grades exactly one item per request, driven by the browser: cPanel has no
 * queue worker, and one request per item stays inside PHP's time limit.
 */
class GradingProcessController extends Controller
{
    private const TRANSCRIPT_MISSING = 'Transkripsi belum tersedia. Tempel transkrip atau pilih nilai tanpa transkrip.';

    public function grade(int $item): JsonResponse
    {
        /** @var ChatUser $chatUser */
        $chatUser = app('chatUser');
        $item = GradingItem::whereIn('grading_batch_id', $chatUser->gradingBatches()->select('id'))->findOrFail($item);

        // Atomic, so a second tab or a double request cannot pay for it twice.
        $claimed = GradingItem::whereKey($item->id)
            ->where('status', GradingItem::QUEUED)
            ->update(['status' => GradingItem::PROCESSING, 'error' => null]);

        if ($claimed === 0) {
            return response()->json(['id' => $item->id, 'status' => $item->status], 409);
        }

        $item->refresh();

        @set_time_limit(180);

        try {
            $this->assertGradable($item);

            $result = (new GradingAi)->grade($item);
        } catch (AiException $e) {
            return $this->finish($item, $this->settledStatus($item, GradingItem::FAILED), ['error' => $e->getMessage()]);
        }

        // A regrade never touches the lecturer's numbers.
        return $this->finish($item, $this->settledStatus($item, GradingItem::READY), [
            'ai_result' => $result,
            'final_scores' => $item->final_scores ?? array_column($result['criteria'], 'score'),
            'error' => null,
        ]);
    }

    private function assertGradable(GradingItem $item): void
    {
        if (! AiModel::chat()->where('model_id', $item->batch->model)->exists()) {
            throw new AiException("Model {$item->batch->model} tidak lagi aktif. Aktifkan lagi di admin → Chat AI.");
        }

        if ($item->type === 'video' && in_array($item->transcript_status, [GradingItem::TRANSCRIPT_PENDING, GradingItem::TRANSCRIPT_FAILED], true)) {
            throw new AiException(self::TRANSCRIPT_MISSING);
        }
    }

    /** An approved grade stays approved whatever happens to a later regrade. */
    private function settledStatus(GradingItem $item, string $status): string
    {
        return $item->approved_at !== null ? GradingItem::APPROVED : $status;
    }

    /** @param  array<string, mixed>  $attributes */
    private function finish(GradingItem $item, string $status, array $attributes): JsonResponse
    {
        $item->update(['status' => $status] + $attributes);

        return response()->json(['id' => $item->id, 'status' => $item->status, 'error' => $item->error]);
    }
}
