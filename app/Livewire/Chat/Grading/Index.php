<?php

declare(strict_types=1);

namespace App\Livewire\Chat\Grading;

use App\Models\AiModel;
use App\Models\ChatMessage;
use App\Models\ChatUser;
use App\Models\GradingBatch;
use App\Models\GradingRubric;
use App\Services\AiException;
use App\Services\AttachmentExtractor;
use App\Services\GradingAi;
use App\Services\GradingFiles;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Rubrics and grading sessions. Every import only fills the form: a rubric is
 * saved when the lecturer presses Simpan, never by the AI.
 */
class Index extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public ?int $sourceMessageId = null;

    public string $name = '';

    public string $guidance = '';

    /** @var list<array{name: string, description: string, max_score: int|float|string, needs_review: bool}> */
    public array $criteria = [];

    public string $importText = '';

    /** @var TemporaryUploadedFile|null */
    public $importFile = null;

    public string $batchName = '';

    public ?int $batchRubricId = null;

    public ?string $batchModel = null;

    public function mount(): void
    {
        $this->batchModel = AiModel::defaultChat()?->model_id;
        $this->criteria = [$this->blankCriterion()];

        $messageId = request()->integer('dari_pesan');

        if ($messageId > 0) {
            $this->sourceMessage($messageId);
            $this->sourceMessageId = $messageId;
        }
    }

    public function importFromText(): void
    {
        $this->validate(['importText' => ['required', 'string', 'max:50000']], [
            'importText.required' => 'Tempel atau jelaskan rubriknya dulu.',
            'importText.max' => 'Teks rubrik terlalu panjang.',
        ]);

        $this->draftFrom($this->importText, 'importText');
    }

    public function importFromDocument(): void
    {
        $this->validate(['importFile' => ['required', 'file', 'mimes:pdf,docx', 'max:10240']], [
            'importFile.required' => 'Pilih dokumen rubrik dulu.',
            'importFile.mimes' => 'Dokumen rubrik harus PDF atau DOCX.',
            'importFile.max' => 'Ukuran dokumen maksimal 10 MB.',
        ]);

        $file = $this->importFile;
        $text = AttachmentExtractor::extract($file->getRealPath(), $file->getMimeType());

        // The source document is never kept.
        $file->delete();
        $this->importFile = null;

        if ($text === null) {
            $this->addError('importFile', 'Dokumen ini tidak berisi teks yang bisa dibaca. PDF hasil scan belum didukung.');

            return;
        }

        $this->draftFrom($text, 'importFile');
    }

    public function importFromMessage(): void
    {
        if ($this->sourceMessageId === null) {
            return;
        }

        $message = $this->sourceMessage($this->sourceMessageId);
        $this->sourceMessageId = null;

        $this->draftFrom((string) $message->content, 'importText');
    }

    public function addCriterion(): void
    {
        $this->criteria[] = $this->blankCriterion();
    }

    public function removeCriterion(int $index): void
    {
        unset($this->criteria[$index]);
        $this->criteria = array_values($this->criteria);
    }

    public function saveRubric(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'guidance' => ['nullable', 'string', 'max:20000'],
            'criteria' => ['required', 'array', 'min:1', 'max:50'],
            'criteria.*.name' => ['required', 'string', 'max:120'],
            'criteria.*.description' => ['nullable', 'string', 'max:5000'],
            'criteria.*.max_score' => ['required', 'numeric', 'gt:0', 'max:100000'],
        ], [
            'name.required' => 'Nama rubrik wajib diisi.',
            'criteria.required' => 'Rubrik butuh minimal satu kriteria.',
            'criteria.min' => 'Rubrik butuh minimal satu kriteria.',
            'criteria.*.name.required' => 'Nama kriteria wajib diisi.',
            'criteria.*.max_score.required' => 'Skor maksimal wajib diisi.',
            'criteria.*.max_score.numeric' => 'Skor maksimal harus berupa angka.',
            'criteria.*.max_score.gt' => 'Skor maksimal harus lebih dari 0.',
        ]);

        $attributes = [
            'name' => trim($this->name),
            'guidance' => trim($this->guidance),
            'criteria' => array_map(fn (array $row) => [
                'name' => trim((string) $row['name']),
                'description' => trim((string) ($row['description'] ?? '')),
                'max_score' => (float) $row['max_score'],
            ], $this->criteria),
        ];

        if ($this->editingId !== null) {
            $this->ownedRubric($this->editingId)->update($attributes);
        } else {
            $this->chatUser()->rubrics()->create($attributes);
        }

        $this->resetForm();
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    public function editRubric(int $id): void
    {
        $rubric = $this->ownedRubric($id);

        $this->editingId = $rubric->id;
        $this->name = $rubric->name;
        $this->guidance = (string) $rubric->guidance;
        $this->criteria = array_map(fn (array $row) => $row + ['needs_review' => false], $rubric->criteria);
        $this->resetErrorBag();
    }

    public function deleteRubric(int $id): void
    {
        $this->ownedRubric($id)->delete();

        if ($this->editingId === $id) {
            $this->resetForm();
        }
    }

    public function createBatch(): void
    {
        $this->validate([
            'batchName' => ['required', 'string', 'max:120'],
            'batchRubricId' => ['required', 'integer'],
            'batchModel' => ['required', Rule::in(AiModel::chat()->pluck('model_id')->all())],
        ], [
            'batchName.required' => 'Nama sesi wajib diisi.',
            'batchRubricId.required' => 'Pilih rubrik dulu.',
            'batchModel.required' => 'Pilih model AI dulu.',
            'batchModel.in' => 'Model ini tidak tersedia.',
        ]);

        $rubric = $this->ownedRubric((int) $this->batchRubricId);

        $batch = $this->chatUser()->gradingBatches()->create([
            'name' => trim($this->batchName),
            'model' => $this->batchModel,
            'rubric_snapshot' => [
                'name' => $rubric->name,
                'guidance' => (string) $rubric->guidance,
                'criteria' => $rubric->criteria,
            ],
        ]);

        $this->redirect(route('chat.grading.batch', $batch), navigate: true);
    }

    public function deleteBatch(int $id): void
    {
        $batch = $this->ownedBatch($id);

        GradingFiles::deleteForBatch($batch);
        $batch->delete();
    }

    public function render()
    {
        $chatUser = $this->chatUser();

        return view('livewire.chat.grading.index', [
            'chatUser' => $chatUser,
            'rubrics' => $chatUser->rubrics()->orderBy('name')->get(),
            'batches' => $chatUser->gradingBatches()->withCount('items')->latest('id')->get(),
            'models' => AiModel::chat()->orderByDesc('is_default')->orderBy('label')->get(),
        ])->layout('layouts.chat', ['title' => 'Penilaian']);
    }

    private function draftFrom(string $source, string $errorField): void
    {
        @set_time_limit(180);

        try {
            $draft = (new GradingAi)->draftRubric($source);
        } catch (AiException $e) {
            $this->addError($errorField, $e->getMessage());

            return;
        }

        $this->editingId = null;
        $this->name = $draft['name'];
        $this->guidance = $draft['guidance'];
        $this->criteria = $draft['criteria'];
        $this->importText = '';
        $this->resetErrorBag();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->guidance = '';
        $this->criteria = [$this->blankCriterion()];
        $this->resetErrorBag();
    }

    /** @return array{name: string, description: string, max_score: string, needs_review: bool} */
    private function blankCriterion(): array
    {
        return ['name' => '', 'description' => '', 'max_score' => '', 'needs_review' => false];
    }

    private function sourceMessage(int $id): ChatMessage
    {
        return ChatMessage::whereIn('chat_conversation_id', $this->chatUser()->conversations()->select('id'))
            ->where('role', 'assistant')
            ->where('is_error', false)
            ->whereNull('image_path')
            ->findOrFail($id);
    }

    private function ownedRubric(int $id): GradingRubric
    {
        return $this->chatUser()->rubrics()->findOrFail($id);
    }

    private function ownedBatch(int $id): GradingBatch
    {
        return $this->chatUser()->gradingBatches()->findOrFail($id);
    }

    private function chatUser(): ChatUser
    {
        return app('chatUser');
    }
}
