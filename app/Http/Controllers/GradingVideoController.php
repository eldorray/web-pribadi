<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AiModel;
use App\Models\ChatUser;
use App\Models\GradingBatch;
use App\Models\GradingItem;
use App\Services\AiClient;
use App\Services\AiException;
use App\Services\GradingFiles;
use App\Support\UploadLimits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Receives what the browser made out of a video: frames and audio chunks. The
 * video itself never reaches the server, so hosting needs no ffmpeg and no disk
 * for it, and audio is transcribed and dropped within the request.
 */
class GradingVideoController extends Controller
{
    private const MAX_SECONDS = 1200;

    private const MAX_FRAMES = 30;

    public function store(Request $request, int $batch): JsonResponse
    {
        $batch = $this->chatUser()->gradingBatches()->findOrFail($batch);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'duration_seconds' => ['required', 'integer', 'min:1', 'max:'.self::MAX_SECONDS],
            'has_audio' => ['required', 'boolean'],
        ], [
            'duration_seconds.max' => 'Video maksimal 20 menit.',
        ]);

        if ($validator->fails()) {
            return $this->rejected($validator->errors()->first());
        }

        if (! AiModel::where('model_id', $batch->model)->value('supports_vision')) {
            return $this->rejected('Model sesi ini tidak bisa membaca gambar. Buat sesi dengan model lain.');
        }

        $name = (string) $request->input('name');
        $transcriptStatus = $request->boolean('has_audio') ? GradingItem::TRANSCRIPT_PENDING : GradingItem::TRANSCRIPT_NO_AUDIO;
        $attributes = [
            'status' => GradingItem::PREPARING,
            'transcript_status' => $transcriptStatus,
            'duration_seconds' => (int) $request->input('duration_seconds'),
            'transcript' => null,
            'files' => [],
            'error' => null,
        ];

        // A video that never finished is resumed by selecting its file again.
        $item = $batch->items()->where('status', GradingItem::PREPARING)->where('original_name', $name)->first();

        if ($item !== null) {
            GradingFiles::deleteForItem($item);
            $item->update($attributes);
        } else {
            if ($batch->items()->count() >= GradingBatch::MAX_ITEMS) {
                return $this->rejected('Satu sesi maksimal '.GradingBatch::MAX_ITEMS.' tugas.');
            }

            $item = $batch->items()->create($attributes + [
                'student_name' => $batch->uniqueStudentName(pathinfo($name, PATHINFO_FILENAME)),
                'type' => 'video',
                'original_name' => $name,
            ]);
        }

        return response()->json([
            'id' => $item->id,
            'student_name' => $item->student_name,
            'status' => $item->status,
            'transcript_status' => $item->transcript_status,
            'frames_url' => route('chat.grading.videos.frames', $item),
            'audio_url' => route('chat.grading.videos.audio', $item),
            'ready_url' => route('chat.grading.videos.ready', $item),
        ]);
    }

    public function frames(Request $request, int $item): JsonResponse
    {
        $item = $this->ownedItem($item);

        $validator = Validator::make($request->all(), [
            'frames' => ['required', 'array', 'min:1', 'max:'.self::MAX_FRAMES],
            'frames.*' => ['required', 'file', 'mimes:jpg,jpeg', 'max:'.$this->maxKilobytes()],
            'at' => ['required', 'array', 'size:'.count((array) $request->file('frames'))],
            'at.*' => ['required', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return $this->rejected('Potongan gambar video tidak valid.');
        }

        $files = (array) $item->files;

        if (count($files) + count($request->file('frames')) > self::MAX_FRAMES) {
            return $this->rejected('Satu video maksimal '.self::MAX_FRAMES.' potongan gambar.');
        }

        foreach ($request->file('frames') as $i => $frame) {
            $files[] = [
                'path' => $frame->store("grading/{$this->chatUser()->getKey()}", 'local'),
                'at' => (int) $request->input("at.{$i}"),
            ];
        }

        $item->update(['files' => $files]);

        return response()->json(['frames' => count($files)]);
    }

    public function audio(Request $request, int $item): JsonResponse
    {
        $item = $this->ownedItem($item);

        $validator = Validator::make($request->all(), [
            'audio' => ['required', 'file', 'mimetypes:audio/wav,audio/x-wav,audio/wave,audio/vnd.wave', 'max:'.$this->maxKilobytes()],
            'offset_seconds' => ['required', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return $this->rejected('Potongan suara video tidak valid.');
        }

        $model = AiModel::defaultTranscribe();

        if ($model === null) {
            return $this->transcriptFailed($item, 'Belum ada model transkripsi aktif. Aktifkan di admin → Chat AI, atau tempel transkrip.');
        }

        @set_time_limit(180);

        $offset = (int) $request->input('offset_seconds');

        try {
            // PHP's upload temp file is enough, and it disappears with the request.
            $text = (new AiClient)->transcribe($request->file('audio')->getRealPath(), $model->model_id, $offset);
        } catch (AiException $e) {
            return $this->transcriptFailed($item, 'Transkripsi gagal: '.$e->getMessage());
        }

        $existing = $offset === 0 ? '' : (string) $item->transcript;

        $item->update(['transcript' => trim($existing."\n".$text)]);

        return response()->json(['ok' => true]);
    }

    public function ready(Request $request, int $item): JsonResponse
    {
        $item = $this->ownedItem($item);

        if ($request->boolean('audio_failed')) {
            $item->update([
                'transcript_status' => GradingItem::TRANSCRIPT_FAILED,
                'error' => 'Suara video ini tidak bisa dibaca browser. Tempel transkrip atau pilih nilai tanpa transkrip.',
            ]);
        }

        if ($item->transcript_status === GradingItem::TRANSCRIPT_FAILED) {
            $item->update(['status' => GradingItem::FAILED]);
        } else {
            $item->update([
                'status' => GradingItem::QUEUED,
                'transcript_status' => $item->transcript_status === GradingItem::TRANSCRIPT_PENDING
                    ? GradingItem::TRANSCRIPT_DONE
                    : $item->transcript_status,
            ]);
        }

        return response()->json(['id' => $item->id, 'status' => $item->status]);
    }

    private function transcriptFailed(GradingItem $item, string $message): JsonResponse
    {
        $item->update(['transcript_status' => GradingItem::TRANSCRIPT_FAILED, 'error' => $message]);

        return $this->rejected($message);
    }

    private function maxKilobytes(): int
    {
        return (int) floor(UploadLimits::bytes() / 1024);
    }

    private function ownedItem(int $id): GradingItem
    {
        return GradingItem::whereIn('grading_batch_id', $this->chatUser()->gradingBatches()->select('id'))->findOrFail($id);
    }

    private function chatUser(): ChatUser
    {
        return app('chatUser');
    }

    private function rejected(string $message): JsonResponse
    {
        return response()->json(['message' => $message], 422);
    }
}
