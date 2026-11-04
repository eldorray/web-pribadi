<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Chat\Grading\BatchPage;
use App\Models\ChatUser;
use App\Models\GradingBatch;
use App\Models\GradingItem;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GradingReviewTest extends TestCase
{
    use RefreshDatabase;

    private ChatUser $user;

    private GradingBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->user = ChatUser::create(['name' => 'Ayu', 'pin' => Hash::make('123456')]);
        $this->batch = $this->makeBatch($this->user);
    }

    private function makeBatch(ChatUser $user): GradingBatch
    {
        return $user->gradingBatches()->create([
            'name' => 'Tugas',
            'model' => 'chat-basic',
            'rubric_snapshot' => ['name' => 'R', 'guidance' => '', 'criteria' => [
                ['name' => 'Isi', 'description' => '', 'max_score' => 10],
                ['name' => 'Bahasa', 'description' => '', 'max_score' => 20],
            ]],
        ]);
    }

    private function aiResult(bool $injection = false): array
    {
        return [
            'criteria' => [
                ['score' => 7, 'reason' => 'Alasan isi', 'evidence' => 'Kutipan isi', 'needs_review' => false],
                ['score' => 15, 'reason' => 'Alasan bahasa', 'evidence' => 'Kutipan bahasa', 'needs_review' => true],
            ],
            'summary' => 'Ringkasan AI',
            'injection_attempt' => $injection,
        ];
    }

    private function item(array $attributes = [], ?GradingBatch $batch = null): GradingItem
    {
        return ($batch ?? $this->batch)->items()->create($attributes + [
            'student_name' => 'Budi',
            'type' => 'document',
            'original_name' => 'budi.docx',
            'status' => GradingItem::READY,
            'extracted_text' => 'Isi esai',
            'ai_result' => $this->aiResult(),
            'final_scores' => [7, 15],
        ]);
    }

    private function page(): Testable
    {
        app()->instance('chatUser', $this->user);

        return Livewire::test(BatchPage::class, ['batch' => $this->batch->id]);
    }

    #[Test]
    public function score_above_maximum_or_below_zero_is_rejected_and_not_saved(): void
    {
        $item = $this->item();

        $this->page()->call('openItem', $item->id)->set('scores', [11, 15])->call('saveReview')->assertHasErrors('scores.0');
        $this->page()->call('openItem', $item->id)->set('scores', [-1, 15])->call('saveReview')->assertHasErrors('scores.0');
        $this->page()->call('openItem', $item->id)->set('scores', ['abc', 15])->call('saveReview')->assertHasErrors('scores.0');

        $this->assertEquals([7, 15], $item->fresh()->final_scores);
    }

    #[Test]
    public function saving_a_review_keeps_ai_result_untouched(): void
    {
        $item = $this->item();

        $this->page()
            ->call('openItem', $item->id)
            ->set('scores', [9.5, 12])
            ->set('note', 'Perlu contoh')
            ->call('saveReview')
            ->assertHasNoErrors();

        $item->refresh();

        $this->assertEquals([9.5, 12], $item->final_scores);
        $this->assertSame('Perlu contoh', $item->lecturer_note);
        $this->assertSame(22.0, $item->aiTotal());
        $this->assertNull($item->approved_at);
    }

    #[Test]
    public function approve_requires_every_score(): void
    {
        $item = $this->item();

        $this->page()
            ->call('openItem', $item->id)
            ->set('scores', [9, ''])
            ->call('approve')
            ->assertHasErrors('scores.1');

        $this->assertNull($item->fresh()->approved_at);
    }

    #[Test]
    public function approve_saves_the_scores_and_finalises(): void
    {
        $item = $this->item();

        $this->page()
            ->call('openItem', $item->id)
            ->set('scores', [9, 18])
            ->call('approve')
            ->assertHasNoErrors();

        $item->refresh();

        $this->assertEquals([9, 18], $item->final_scores);
        $this->assertNotNull($item->approved_at);
        $this->assertSame(GradingItem::APPROVED, $item->status);
    }

    #[Test]
    public function lecturer_can_grade_by_hand_when_the_ai_failed(): void
    {
        $item = $this->item(['status' => GradingItem::FAILED, 'ai_result' => null, 'final_scores' => null, 'error' => 'Gagal']);

        $this->page()
            ->call('openItem', $item->id)
            ->set('scores', [5, 5])
            ->call('approve')
            ->assertHasNoErrors();

        $this->assertSame(GradingItem::APPROVED, $item->fresh()->status);
    }

    #[Test]
    public function an_item_still_being_processed_cannot_be_approved(): void
    {
        $item = $this->item(['status' => GradingItem::PROCESSING]);

        $this->page()->call('openItem', $item->id)->set('scores', [5, 5])->call('approve');

        $this->assertNull($item->fresh()->approved_at);
    }

    #[Test]
    public function changing_a_score_after_approval_clears_approved_at(): void
    {
        $item = $this->item(['status' => GradingItem::APPROVED, 'approved_at' => now()]);

        $this->page()->call('openItem', $item->id)->set('scores', [1, 15])->call('saveReview')->assertHasNoErrors();

        $item->refresh();

        $this->assertNull($item->approved_at);
        $this->assertSame(GradingItem::READY, $item->status);
    }

    #[Test]
    public function saving_only_a_note_keeps_the_approval(): void
    {
        $item = $this->item(['status' => GradingItem::APPROVED, 'approved_at' => now()]);

        $this->page()->call('openItem', $item->id)->set('note', 'Catatan')->call('saveReview')->assertHasNoErrors();

        $this->assertNotNull($item->fresh()->approved_at);
        $this->assertSame('Catatan', $item->fresh()->lecturer_note);
    }

    #[Test]
    public function regrade_queues_the_item_without_touching_final_scores(): void
    {
        $item = $this->item(['final_scores' => [1, 2], 'lecturer_note' => 'Catatan']);

        $this->page()->call('regrade', $item->id)->assertDispatched('grading-run');

        $item->refresh();

        $this->assertSame(GradingItem::QUEUED, $item->status);
        $this->assertEquals([1, 2], $item->final_scores);
        $this->assertSame('Catatan', $item->lecturer_note);
    }

    #[Test]
    public function an_unreadable_document_is_not_sent_for_regrading(): void
    {
        $item = $this->item(['status' => GradingItem::FAILED, 'extracted_text' => null, 'ai_result' => null, 'error' => 'Tidak terbaca']);

        $this->page()->call('regrade', $item->id);

        $this->assertSame(GradingItem::FAILED, $item->fresh()->status);
    }

    private function failedVideo(): GradingItem
    {
        return $this->item([
            'type' => 'video',
            'original_name' => 'budi.mp4',
            'status' => GradingItem::FAILED,
            'transcript_status' => GradingItem::TRANSCRIPT_FAILED,
            'ai_result' => null,
            'final_scores' => null,
            'extracted_text' => null,
            'error' => 'Transkripsi gagal',
        ]);
    }

    #[Test]
    public function save_transcript_requeues_and_marks_done(): void
    {
        $item = $this->failedVideo();

        $this->page()
            ->call('openItem', $item->id)
            ->set('transcriptDraft', 'Transkrip tempelan')
            ->call('saveTranscript')
            ->assertHasNoErrors()
            ->assertDispatched('grading-run');

        $item->refresh();

        $this->assertSame('Transkrip tempelan', $item->transcript);
        $this->assertSame(GradingItem::TRANSCRIPT_DONE, $item->transcript_status);
        $this->assertSame(GradingItem::QUEUED, $item->status);
        $this->assertNull($item->error);
    }

    #[Test]
    public function grade_without_transcript_marks_skipped_and_queues(): void
    {
        $item = $this->failedVideo();

        $this->page()->call('gradeWithoutTranscript', $item->id)->assertDispatched('grading-run');

        $item->refresh();

        $this->assertSame(GradingItem::TRANSCRIPT_SKIPPED, $item->transcript_status);
        $this->assertSame(GradingItem::QUEUED, $item->status);
    }

    #[Test]
    public function failed_transcription_offers_both_ways_forward(): void
    {
        $this->page()
            ->call('openItem', $this->failedVideo()->id)
            ->assertSee('Tempel transkrip')
            ->assertSee('Nilai tanpa transkrip');
    }

    #[Test]
    public function injection_attempt_warning_is_shown(): void
    {
        $item = $this->item(['ai_result' => $this->aiResult(true)]);

        $this->page()->assertSee('coba memberi perintah')->call('openItem', $item->id)->assertSee('coba memberi perintah');
    }

    #[Test]
    public function ai_total_is_labelled_as_a_proposal(): void
    {
        $this->item();

        $this->page()->assertSee('Usulan AI 22/30')->assertSee('Belum disetujui');
    }

    #[Test]
    public function final_total_appears_only_after_approval(): void
    {
        $this->item(['status' => GradingItem::APPROVED, 'approved_at' => now(), 'final_scores' => [10, 20]]);

        $this->page()->assertSee('Nilai 30/30')->assertSee('100');
    }

    #[Test]
    public function needs_review_is_flagged_in_the_list_and_detail(): void
    {
        $item = $this->item();

        $this->page()->assertSee('perlu dicek')->call('openItem', $item->id)
            ->assertSee('Alasan bahasa')
            ->assertSee('Kutipan bahasa');
    }

    #[Test]
    public function rename_student_trims_and_ignores_empty(): void
    {
        $item = $this->item();

        $this->page()->call('renameStudent', $item->id, '  Budi Santoso  ');
        $this->assertSame('Budi Santoso', $item->fresh()->student_name);

        $this->page()->call('renameStudent', $item->id, '   ');
        $this->assertSame('Budi Santoso', $item->fresh()->student_name);
    }

    #[Test]
    public function delete_item_removes_its_files(): void
    {
        Storage::disk('local')->put('grading/1/a.jpg', 'JPG');
        $item = $this->item(['files' => [['path' => 'grading/1/a.jpg', 'name' => 'a.jpg', 'mime' => 'image/jpeg']]]);

        $this->page()->call('openItem', $item->id)->call('deleteItem', $item->id)->assertSet('openItemId', null);

        Storage::disk('local')->assertMissing('grading/1/a.jpg');
        $this->assertSame(0, GradingItem::count());
    }

    #[Test]
    public function foreign_batch_and_item_are_404(): void
    {
        $other = ChatUser::create(['name' => 'Rina', 'pin' => Hash::make('654321')]);
        $foreignBatch = $this->makeBatch($other);
        $foreign = $this->item([], $foreignBatch);

        app()->instance('chatUser', $this->user);

        $this->assertThrows(fn () => Livewire::test(BatchPage::class, ['batch' => $foreignBatch->id]), ModelNotFoundException::class);

        foreach (['openItem', 'regrade', 'gradeWithoutTranscript', 'deleteItem'] as $method) {
            $this->assertThrows(fn () => $this->page()->call($method, $foreign->id), ModelNotFoundException::class);
        }

        $this->assertThrows(fn () => $this->page()->call('renameStudent', $foreign->id, 'X'), ModelNotFoundException::class);
        $this->assertNotNull($foreign->fresh());
    }

    #[Test]
    public function an_item_from_another_batch_of_the_same_user_is_404(): void
    {
        $sibling = $this->item([], $this->makeBatch($this->user));

        $this->assertThrows(fn () => $this->page()->call('openItem', $sibling->id), ModelNotFoundException::class);
    }

    #[Test]
    public function student_name_is_escaped(): void
    {
        $this->item(['student_name' => '<b>x</b>']);

        $this->page()->assertDontSeeHtml('<b>x</b>')->assertSeeHtml('&lt;b&gt;x&lt;/b&gt;');
    }

    #[Test]
    public function preparing_video_explains_how_to_resume(): void
    {
        $this->item(['type' => 'video', 'status' => GradingItem::PREPARING, 'ai_result' => null, 'final_scores' => null]);

        $this->page()->assertSee('Pilih ulang file video');
    }
}
