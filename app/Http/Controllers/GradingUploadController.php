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
use Illuminate\Http\UploadedFile;
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

        $name = $file->getClientOriginalName();
        [$student, $order] = $isImage ? $this->splitImageNumber(pathinfo($name, PATHINFO_FILENAME)) : [pathinfo($name, PATHINFO_FILENAME), 0];

        if ($isImage && ($existing = $this->submissionToJoin($batch, $student, $order)) !== null) {
            return $this->appendImage($existing, $file, $order);
        }

        if ($batch->items()->count() >= GradingBatch::MAX_ITEMS) {
            return $this->rejected('Satu sesi maksimal '.GradingBatch::MAX_ITEMS.' tugas.');
        }

        $disk = Storage::disk('local');
        $path = $file->store("grading/{$chatUser->getKey()}", 'local');
        $mime = $disk->mimeType($path) ?: 'application/octet-stream';
        $text = $isImage ? null : AttachmentExtractor::extract($disk->path($path), $mime);
        $unreadable = ! $isImage && $text === null;

        $item = $batch->items()->create([
            'student_name' => $batch->uniqueStudentName($student),
            'type' => $isImage ? 'image' : 'document',
            'original_name' => $name,
            'status' => $unreadable ? GradingItem::FAILED : GradingItem::QUEUED,
            'files' => [['path' => $path, 'name' => $name, 'mime' => $mime, 'order' => $order]],
            'extracted_text' => $text,
            'error' => $unreadable ? 'Dokumen ini tidak berisi teks yang bisa dibaca. PDF hasil scan belum didukung.' : null,
        ]);

        return response()->json(['id' => $item->id, 'student_name' => $item->student_name, 'status' => $item->status]);
    }

    /** Add an image to a submission from its detail panel, whatever the file is called. */
    public function addImage(Request $request, int $item): JsonResponse
    {
        $item = $this->ownedItem($item);

        if ($item->type !== 'image') {
            return $this->rejected('Hanya tugas gambar yang bisa ditambah gambarnya.');
        }

        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.(int) floor(UploadLimits::bytes() / 1024)],
        ], [
            'file.required' => 'Pilih gambar dulu.',
            'file.uploaded' => 'Gambar terlalu besar untuk hosting ini.',
            'file.mimes' => 'File harus gambar (JPG, PNG, WEBP).',
            'file.max' => 'Ukuran gambar melebihi batas '.round(UploadLimits::bytes() / 1048576, 1).' MB.',
        ]);

        if ($validator->fails()) {
            return $this->rejected($validator->errors()->first('file'));
        }

        $last = max([0, ...array_column((array) $item->files, 'order')]);

        return $this->appendImage($item, $request->file('file'), $last + 1);
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

    /**
     * "Group 1 - 2" is image 2 of Group 1. Only a spaced dash and at most two
     * digits count: "Group-1", "Group 1" and "Ayu - 1000000001" are all whole names
     * of their own, and silently merging two students' work would be far worse
     * than having to add an image by hand.
     *
     * @return array{0: string, 1: int}
     */
    private function splitImageNumber(string $name): array
    {
        return preg_match('/^(.+?)\s+-\s+(\d{1,2})$/u', $name, $match) === 1
            ? [trim($match[1]), (int) $match[2]]
            : [$name, 0];
    }

    /** A plain "Group 1" joins only a submission already made of numbered images. */
    private function submissionToJoin(GradingBatch $batch, string $student, int $order): ?GradingItem
    {
        $existing = $batch->items()->where('type', 'image')->where('student_name', $student)->first();

        if ($existing === null) {
            return null;
        }

        return $order > 0 || max([0, ...array_column((array) $existing->files, 'order')]) > 0 ? $existing : null;
    }

    /** The proposal no longer covers the whole submission; the lecturer's numbers stay. */
    private function appendImage(GradingItem $item, UploadedFile $file, int $order): JsonResponse
    {
        if ($item->status === GradingItem::PROCESSING) {
            return $this->rejected('Tugas ini sedang dinilai. Tunggu sampai selesai, lalu tambah gambarnya.');
        }

        $files = (array) $item->files;
        $name = $file->getClientOriginalName();

        if (count($files) >= GradingItem::MAX_IMAGES) {
            return $this->rejected('Satu tugas maksimal '.GradingItem::MAX_IMAGES.' gambar.');
        }

        if (in_array($name, array_column($files, 'name'), true)) {
            return $this->rejected("Gambar {$name} sudah ada di tugas ini.");
        }

        $disk = Storage::disk('local');
        $path = $file->store("grading/{$item->batch->chat_user_id}", 'local');

        $files[] = ['path' => $path, 'name' => $name, 'mime' => $disk->mimeType($path) ?: 'image/jpeg', 'order' => $order];
        usort($files, fn (array $a, array $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

        $item->update(['files' => $files, 'status' => GradingItem::QUEUED, 'error' => null]);

        return response()->json(['id' => $item->id, 'student_name' => $item->student_name, 'status' => $item->status]);
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
