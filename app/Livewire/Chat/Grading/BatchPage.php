<?php

declare(strict_types=1);

namespace App\Livewire\Chat\Grading;

use App\Models\ChatUser;
use App\Models\GradingBatch;
use App\Models\GradingItem;
use App\Services\GradingFiles;
use App\Support\UploadLimits;
use Illuminate\Support\Str;
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
        'id', 'grading_batch_id', 'student_name', 'type', 'status', 'transcript_status',
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
        $owned = $this->chatUser()->gradingBatches()->findOrFail($batch);
        $owned->releaseStaleItems();

        $this->batchId = $owned->getKey();
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
        $this->validateScores(required: false);

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

        if (in_array($item->status, [GradingItem::PREPARING, GradingItem::QUEUED, GradingItem::PROCESSING], true)) {
            $this->addError('scores', 'Tunggu sampai tugas ini selesai diproses.');

            return;
        }

        $this->validateScores(required: true);

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

    public function saveTranscript(): void
    {
        $this->validate(['transcriptDraft' => ['required', 'string', 'max:400000']], [
            'transcriptDraft.required' => 'Tempel transkripnya dulu.',
            'transcriptDraft.max' => 'Transkrip terlalu panjang.',
        ]);

        $this->ownedItem((int) $this->openItemId)->update([
            'transcript' => trim($this->transcriptDraft),
            'transcript_status' => GradingItem::TRANSCRIPT_DONE,
            'status' => GradingItem::QUEUED,
            'error' => null,
        ]);

        $this->dispatch('grading-run');
    }

    public function gradeWithoutTranscript(int $id): void
    {
        $this->ownedItem($id)->update([
            'transcript_status' => GradingItem::TRANSCRIPT_SKIPPED,
            'status' => GradingItem::QUEUED,
            'error' => null,
        ]);

        $this->dispatch('grading-run');
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

        GradingFiles::deleteForItem($item);
        $item->delete();

        if ($this->openItemId === $id) {
            $this->closeItem();
        }
    }

    public function render()
    {
        $batch = $this->batch();
        $items = $batch->items()->select(self::LIST_COLUMNS)->orderBy('id')->get();
        $items->each->setRelation('batch', $batch);

        $open = $this->openItemId !== null ? $batch->items()->find($this->openItemId) : null;
        $open?->setRelation('batch', $batch);

        return view('livewire.chat.grading.batch', [
            'batch' => $batch,
            'items' => $items,
            'open' => $open,
            'criteria' => $batch->criteria(),
            'maxScore' => $batch->maxScore(),
            'uploadLimit' => UploadLimits::bytes(),
        ])->layout('layouts.chat', ['title' => $batch->name.' · Penilaian']);
    }

    private function validateScores(bool $required): void
    {
        $rules = [];
        $messages = [];

        foreach ($this->batch()->criteria() as $i => $criterion) {
            $rules["scores.{$i}"] = [$required ? 'required' : 'nullable', 'numeric', 'min:0', "max:{$criterion['max_score']}"];
            $messages["scores.{$i}.required"] = 'Isi skor kriteria ini.';
            $messages["scores.{$i}.numeric"] = 'Skor harus berupa angka.';
            $messages["scores.{$i}.min"] = 'Skor tidak boleh kurang dari 0.';
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
