<?php

declare(strict_types=1);

namespace App\Livewire\Chat\Grading;

use App\Models\ChatUser;
use App\Models\GradingBatch;
use App\Support\UploadLimits;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * One grading session: uploads, progress, and the lecturer's review.
 */
class BatchPage extends Component
{
    #[Locked]
    public int $batchId;

    public function mount(int $batch): void
    {
        $this->batchId = $this->chatUser()->gradingBatches()->findOrFail($batch)->getKey();
    }

    public function render()
    {
        return view('livewire.chat.grading.batch', [
            'batch' => $this->batch(),
            'uploadLimit' => UploadLimits::bytes(),
        ])->layout('layouts.chat', ['title' => 'Penilaian']);
    }

    private function batch(): GradingBatch
    {
        return $this->chatUser()->gradingBatches()->findOrFail($this->batchId);
    }

    private function chatUser(): ChatUser
    {
        return app('chatUser');
    }
}
