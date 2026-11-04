<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AiModel;
use App\Models\ChatUser;
use App\Models\GradingBatch;
use App\Models\GradingItem;
use App\Services\AttachmentExtractor;
use App\Support\UploadLimits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Takes one image or essay per request. The browser sends files one at a time,
 * so a big batch never hits post_max_size and each file reports its own result.
 */
class GradingUploadController extends Controller
{
    public function store(Request $request, int $batch): JsonResponse
    {
        /** @var ChatUser $chatUser */
        $chatUser = app('chatUser');
        $batch = $chatUser->gradingBatches()->findOrFail($batch);

        if ($batch->items()->count() >= GradingBatch::MAX_ITEMS) {
            return $this->rejected('Satu sesi maksimal '.GradingBatch::MAX_ITEMS.' tugas.');
        }

        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf,docx', 'max:'.(int) floor(UploadLimits::bytes() / 1024)],
        ], [
            'file.required' => 'Pilih file dulu.',
            'file.uploaded' => 'File terlalu besar untuk hosting ini.',
            'file.mimes' => 'File harus gambar (JPG, PNG, WEBP), PDF, atau DOCX.',
            'file.max' => 'Ukuran file melebihi batas '.round(UploadLimits::bytes() / 1048576, 1).' MB.',
        ]);

        if ($validator->fails()) {
            return $this->rejected($validator->errors()->first('file'));
        }

        $file = $request->file('file');
        $isImage = str_starts_with((string) $file->getMimeType(), 'image/');

        if ($isImage && ! AiModel::where('model_id', $batch->model)->value('supports_vision')) {
            return $this->rejected('Model sesi ini tidak bisa membaca gambar. Buat sesi dengan model lain.');
        }

        $disk = Storage::disk('local');
        $path = $file->store("grading/{$chatUser->getKey()}", 'local');
        $name = $file->getClientOriginalName();
        $mime = $disk->mimeType($path) ?: 'application/octet-stream';
        $text = $isImage ? null : AttachmentExtractor::extract($disk->path($path), $mime);
        $unreadable = ! $isImage && $text === null;

        $item = $batch->items()->create([
            'student_name' => $batch->uniqueStudentName(pathinfo($name, PATHINFO_FILENAME)),
            'type' => $isImage ? 'image' : 'document',
            'original_name' => $name,
            'status' => $unreadable ? GradingItem::FAILED : GradingItem::QUEUED,
            'files' => [['path' => $path, 'name' => $name, 'mime' => $mime]],
            'extracted_text' => $text,
            'error' => $unreadable ? 'Dokumen ini tidak berisi teks yang bisa dibaca. PDF hasil scan belum didukung.' : null,
        ]);

        return response()->json(['id' => $item->id, 'student_name' => $item->student_name, 'status' => $item->status]);
    }

    public function file(int $item, int $index): Response
    {
        $item = $this->ownedItem($item);
        $path = $item->files[$index]['path'] ?? null;
        $disk = Storage::disk('local');

        abort_unless(is_string($path) && $disk->exists($path), 404);

        return $disk->response($path, (string) ($item->files[$index]['name'] ?? basename($path)), [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function ownedItem(int $id): GradingItem
    {
        /** @var ChatUser $chatUser */
        $chatUser = app('chatUser');

        return GradingItem::whereIn('grading_batch_id', $chatUser->gradingBatches()->select('id'))->findOrFail($id);
    }

    private function rejected(string $message): JsonResponse
    {
        return response()->json(['message' => $message], 422);
    }
}
