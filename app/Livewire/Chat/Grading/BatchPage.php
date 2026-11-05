<?php

declare(strict_types=1);

namespace App\Livewire\Chat\Grading;

use App\Models\ChatUser;
use App\Models\GradingBatch;
use App\Models\GradingItem;
use App\Services\AiException;
use App\Services\GradingAi;
use App\Services\GradingFiles;
use App\Support\UploadLimits;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * One grading session: uploads, progress, and the lecturer's review. AI numbers
 * stay a proposal here; only approve() makes a grade final.
 */
class BatchPage extends Component
{
    /** Columns the list needs; transcripts and essay text can be very large. */
    private const LIST_COLUMNS = [
        'id', 'grading_batch_id', 'student_name', 'type', 'status', 'transcript_status', 'files',
        'ai_result', 'final_scores', 'approved_at', 'error',
    ];

    #[Locked]
    public int $batchId;

    #[Locked]
    public ?int $openItemId = null;

    /** @var array<int, int|float|string|null> */
    public array $scores = [];

    public string $note = '';

    public string $transcriptDraft = '';

    public function mount(int $batch): void
    {
        $this->batchId = $this->chatUser()->gradingBatches()->findOrFail($batch)->getKey();
    }

    public function openItem(int $id): void
    {
        $item = $this->ownedItem($id);

        $this->openItemId = $item->id;
        $this->scores = $item->final_scores ?? array_fill(0, count($this->batch()->criteria()), null);
        $this->note = (string) $item->lecturer_note;
        $this->transcriptDraft = (string) $item->transcript;
        $this->resetErrorBag();
    }

    public function closeItem(): void
    {
        $this->openItemId = null;
        $this->resetErrorBag();
    }

    public function saveReview(): void
    {
        if (! $this->scoresAreValid(required: false)) {
            return;
        }

        $item = $this->ownedItem((int) $this->openItemId);
        $scores = $this->normalisedScores();
        $changed = $scores != $item->final_scores;

        $item->update(['final_scores' => $scores, 'lecturer_note' => trim($this->note)] + (
            $changed && $item->approved_at !== null
                ? ['approved_at' => null, 'status' => GradingItem::READY]
                : []
        ));
    }

    public function approve(): void
    {
        $item = $this->ownedItem((int) $this->openItemId);

        // A regrade waiting in line only replaces the proposal, so it blocks nothing.
        $firstGradingPending = $item->ai_result === null && in_array($item->status, [GradingItem::QUEUED, GradingItem::PROCESSING], true);

        if ($item->status === GradingItem::PREPARING || $firstGradingPending) {
            $this->addError('approve', 'Tunggu sampai tugas ini selesai diproses.');

            return;
        }

        if (! $this->scoresAreValid(required: true)) {
            return;
        }

        $item->update([
            'final_scores' => $this->normalisedScores(),
            'lecturer_note' => trim($this->note),
            'approved_at' => now(),
            'status' => GradingItem::APPROVED,
        ]);
    }

    public function regrade(int $id): void
    {
        $item = $this->ownedItem($id);

        if ($item->type === 'document' && trim((string) $item->extracted_text) === '') {
            return;
        }

        if ($item->status === GradingItem::PROCESSING || $item->status === GradingItem::PREPARING) {
            return;
        }

        $item->update(['status' => GradingItem::QUEUED, 'error' => null]);
        $this->dispatch('grading-run');
    }

    public function removeImage(int $id, int $index): void
    {
        $item = $this->ownedItem($id);
        $files = array_values((array) $item->files);

        if ($item->type !== 'image' || ! isset($files[$index]) || count($files) < 2 || $item->status === GradingItem::PROCESSING) {
            return;
        }

        Storage::disk('local')->delete((string) ($files[$index]['path'] ?? ''));
        unset($files[$index]);

        $item->update(['files' => array_values($files), 'status' => GradingItem::QUEUED, 'error' => null]);
        $this->dispatch('grading-run');
    }

    public function saveTranscript(): void
    {
        $this->validate(['transcriptDraft' => ['required', 'string', 'max:400000']], [
            'transcriptDraft.required' => 'Tempel transkripnya dulu.',
            'transcriptDraft.max' => 'Transkrip terlalu panjang.',
        ]);

        $item = $this->ownedItem((int) $this->openItemId);

        if ($item->status === GradingItem::PROCESSING) {
            $this->addError('transcriptDraft', 'AI masih menilai tugas ini. Tunggu sampai selesai.');

            return;
        }

        $item->update([
            'transcript' => trim($this->transcriptDraft),
            'transcript_status' => GradingItem::TRANSCRIPT_DONE,
            'status' => GradingItem::QUEUED,
            'error' => null,
        ]);

        $this->dispatch('grading-run');
    }

    public function gradeWithoutTranscript(int $id): void
    {
        $item = $this->ownedItem($id);

        if ($item->status === GradingItem::PROCESSING) {
            return;
        }

        $item->update([
            'transcript_status' => GradingItem::TRANSCRIPT_SKIPPED,
            'status' => GradingItem::QUEUED,
            'error' => null,
        ]);

        $this->dispatch('grading-run');
    }

    /**
     * Fill the note from the scores on screen. Nothing is stored here: the draft
     * is the lecturer's to edit, and it is saved by Simpan or Setujui like any note.
     */
    public function generateNote(): void
    {
        try {
            $this->validateScores(required: true);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());
            $this->addError('note', 'Isi skor semua kriteria dulu, karena catatan dibuat dari skor itu.');

            return;
        }

        @set_time_limit(180);

        try {
            $this->note = (new GradingAi)->draftNote($this->ownedItem((int) $this->openItemId), $this->normalisedScores());
        } catch (AiException $e) {
            $this->addError('note', $e->getMessage());
        }
    }

    public function renameStudent(int $id, string $name): void
    {
        $item = $this->ownedItem($id);
        $name = trim($name);

        if ($name !== '') {
            $item->update(['student_name' => Str::limit($name, 200, '')]);
        }
    }

    public function deleteItem(int $id): void
    {
        $item = $this->ownedItem($id);

        // The running request would come back to a missing row. It clears within
        // STALE_MINUTES at worst, so a stuck task never stays undeletable.
        if ($item->status === GradingItem::PROCESSING) {
            return;
        }

        GradingFiles::deleteForItem($item);
        $item->delete();

        if ($this->openItemId === $id) {
            $this->closeItem();
        }
    }

    public function render()
    {
        $batch = $this->batch();
        $batch->releaseStaleItems();

        $items = $batch->items()->select(self::LIST_COLUMNS)->orderBy('id')->get();
        $items->each->setRelation('batch', $batch);

        $open = $this->openItemId !== null ? $batch->items()->find($this->openItemId) : null;
        $open?->setRelation('batch', $batch);

        // Opened while the AI was still working: bring in its scores once they
        // exist, but never over anything the lecturer has typed.
        if ($open?->final_scores !== null && array_filter($this->scores, fn ($score) => $score !== null && $score !== '') === []) {
            $this->scores = $open->final_scores;
        }

        return view('livewire.chat.grading.batch', [
            'batch' => $batch,
            'items' => $items,
            'open' => $open,
            'grading' => $items->contains(fn (GradingItem $item): bool => $item->isBusy()),
            'criteria' => $batch->criteria(),
            'maxScore' => $batch->maxScore(),
            'aiMaxScore' => $batch->aiMaxScore(),
            'uploadLimit' => UploadLimits::bytes(),
            'maxFiles' => UploadLimits::files(),
        ])->layout('layouts.chat', ['title' => $batch->name.' · Penilaian']);
    }

    /**
     * The detail panel is long, so an error beside a score at the top can be off
     * screen when the buttons are pressed. Name the criteria next to the buttons.
     */
    private function scoresAreValid(bool $required): bool
    {
        try {
            $this->validateScores($required);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());
            $this->addError('approve', $this->scoreSummary(array_keys($e->validator->errors()->messages())));

            return false;
        }

        return true;
    }

    /** @param  list<string>  $fields */
    private function scoreSummary(array $fields): string
    {
        $criteria = $this->batch()->criteria();
        $missing = [];
        $invalid = [];

        foreach ($fields as $field) {
            if (! preg_match('/^scores\.(\d+)$/', $field, $match) || ! isset($criteria[(int) $match[1]])) {
                continue;
            }

            $score = $this->scores[(int) $match[1]] ?? null;

            if ($score === null || $score === '') {
                $missing[] = $criteria[(int) $match[1]]['name'];
            } else {
                $invalid[] = $criteria[(int) $match[1]]['name'];
            }
        }

        $parts = array_filter([
            $missing !== [] ? 'Isi skor: '.implode(', ', $missing).'.' : null,
            $invalid !== [] ? 'Periksa skor: '.implode(', ', $invalid).'.' : null,
        ]);

        return 'Belum bisa disetujui. '.implode(' ', $parts);
    }

    private function validateScores(bool $required): void
    {
        $rules = [];
        $messages = [];

        foreach ($this->batch()->criteria() as $i => $criterion) {
            $min = $criterion['min_score'] ?? 0;
            $rules["scores.{$i}"] = [
                $required ? 'required' : 'nullable',
                isset($criterion['weight']) ? 'integer' : 'numeric',
                "min:{$min}",
                "max:{$criterion['max_score']}",
            ];
            $messages["scores.{$i}.required"] = 'Isi skor kriteria ini.';
            $messages["scores.{$i}.numeric"] = 'Skor harus berupa angka.';
            $messages["scores.{$i}.integer"] = 'Skor harus bilangan bulat.';
            $messages["scores.{$i}.min"] = "Skor tidak boleh kurang dari {$min}.";
            $messages["scores.{$i}.max"] = "Skor maksimal {$criterion['max_score']}.";
        }

        $this->validate($rules + ['note' => ['nullable', 'string', 'max:5000']], $messages);
    }

    /** @return list<float|null> */
    private function normalisedScores(): array
    {
        $scores = [];

        foreach (array_keys($this->batch()->criteria()) as $i) {
            $value = $this->scores[$i] ?? null;
            $scores[] = $value === null || $value === '' ? null : (float) $value;
        }

        return $scores;
    }

    private function batch(): GradingBatch
    {
        return $this->chatUser()->gradingBatches()->findOrFail($this->batchId);
    }

    private function ownedItem(int $id): GradingItem
    {
        return $this->batch()->items()->findOrFail($id);
    }

    private function chatUser(): ChatUser
    {
        return app('chatUser');
    }
}
