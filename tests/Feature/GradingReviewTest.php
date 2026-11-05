<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Chat\Grading\BatchPage;
use App\Models\ChatUser;
use App\Models\GradingBatch;
use App\Models\GradingItem;
use App\Models\SiteSetting;
use App\Services\AiClient;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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
    public function an_item_the_ai_has_not_finished_cannot_be_approved(): void
    {
        $item = $this->item(['status' => GradingItem::PROCESSING, 'ai_result' => null, 'final_scores' => null]);

        $this->page()
            ->call('openItem', $item->id)
            ->set('scores', [5, 5])
            ->call('approve')
            ->assertHasErrors('approve');

        $this->assertNull($item->fresh()->approved_at);
    }

    #[Test]
    public function a_video_still_being_prepared_cannot_be_approved(): void
    {
        $item = $this->item(['status' => GradingItem::PREPARING, 'ai_result' => null, 'final_scores' => null]);

        $this->page()->call('openItem', $item->id)->set('scores', [5, 5])->call('approve')->assertHasErrors('approve');

        $this->assertNull($item->fresh()->approved_at);
    }

    #[Test]
    public function an_item_waiting_for_a_regrade_can_still_be_approved(): void
    {
        $item = $this->item(['status' => GradingItem::QUEUED]);

        $this->page()->call('openItem', $item->id)->set('scores', [5, 5])->call('approve')->assertHasNoErrors();

        $this->assertNotNull($item->fresh()->approved_at);
    }

    #[Test]
    public function approval_failure_names_the_missing_criteria_next_to_the_button(): void
    {
        $this->humanOnlyBatch();
        $item = $this->humanOnlyItem();

        $this->page()
            ->call('openItem', $item->id)
            ->call('approve')
            ->assertHasErrors(['approve', 'scores.0'])
            ->assertSee('Belum bisa disetujui')
            ->assertSee('Pelafalan');
    }

    #[Test]
    public function approval_failure_for_a_score_over_the_maximum_also_names_it(): void
    {
        $item = $this->item();

        $this->page()
            ->call('openItem', $item->id)
            ->set('scores', [99, 15])
            ->call('approve')
            ->assertHasErrors(['approve', 'scores.0'])
            ->assertSee('Periksa skor: Isi');

        $this->assertNull($item->fresh()->approved_at);
    }

    #[Test]
    public function reopening_after_a_grade_finished_shows_the_new_scores(): void
    {
        $item = $this->item(['status' => GradingItem::PROCESSING, 'ai_result' => null, 'final_scores' => null]);

        $page = $this->page()->call('openItem', $item->id);

        $item->update(['status' => GradingItem::READY, 'ai_result' => $this->aiResult(), 'final_scores' => [7, 15]]);

        $page->call('$refresh')->assertSet('scores', [7, 15]);
    }

    #[Test]
    public function a_refresh_keeps_scores_the_lecturer_is_typing(): void
    {
        $item = $this->item();

        $this->page()
            ->call('openItem', $item->id)
            ->set('scores', [9, 15])
            ->call('$refresh')
            ->assertSet('scores', [9, 15]);
    }

    #[Test]
    public function required_lecturer_criterion_says_so_on_the_form(): void
    {
        $this->humanOnlyBatch();

        $this->page()->call('openItem', $this->humanOnlyItem()->id)->assertSee('Wajib diisi dosen');
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

    #[Test]
    public function refreshing_the_page_releases_a_stuck_item(): void
    {
        $item = $this->item(['status' => GradingItem::PROCESSING]);
        $page = $this->page();

        GradingItem::whereKey($item->id)->toBase()->update(['updated_at' => now()->subMinutes(11)]);

        $page->call('$refresh');

        $this->assertSame(GradingItem::QUEUED, $item->fresh()->status);
    }

    #[Test]
    public function an_item_being_graded_explains_what_to_do_if_it_stalls(): void
    {
        $this->item(['status' => GradingItem::PROCESSING]);

        $this->page()->assertSee('10 menit');
    }

    private function humanOnlyBatch(): void
    {
        $this->batch->update(['rubric_snapshot' => ['name' => 'R', 'guidance' => '', 'criteria' => [
            ['name' => 'Pelafalan', 'description' => '', 'max_score' => 10, 'human_only' => true],
            ['name' => 'Bahasa', 'description' => '', 'max_score' => 20],
        ]]]);
    }

    private function humanOnlyItem(): GradingItem
    {
        return $this->item([
            'final_scores' => [null, 15],
            'ai_result' => [
                'criteria' => [
                    ['score' => null, 'reason' => 'Dinilai dosen', 'evidence' => '', 'needs_review' => false],
                    ['score' => 15, 'reason' => 'Alasan bahasa', 'evidence' => '', 'needs_review' => false],
                ],
                'summary' => '',
                'injection_attempt' => false,
            ],
        ]);
    }

    #[Test]
    public function ai_total_is_shown_against_the_criteria_the_ai_scored(): void
    {
        $this->humanOnlyBatch();
        $this->humanOnlyItem();

        $this->page()->assertSee('Usulan AI 15/20');
    }

    #[Test]
    public function human_only_criterion_shows_no_ai_score_and_must_be_filled(): void
    {
        $this->humanOnlyBatch();
        $item = $this->humanOnlyItem();

        $this->page()
            ->call('openItem', $item->id)
            ->assertSee('Dinilai dosen')
            ->assertDontSee('Usulan AI: 0/10')
            ->call('approve')
            ->assertHasErrors('scores.0');

        $this->assertNull($item->fresh()->approved_at);
    }

    private function weightedBatch(): void
    {
        $this->batch->update(['rubric_snapshot' => ['name' => 'R', 'guidance' => '', 'criteria' => [
            ['name' => 'Relevansi', 'description' => '', 'max_score' => 4, 'min_score' => 1, 'weight' => 60],
            ['name' => 'Kejelasan', 'description' => '', 'max_score' => 4, 'min_score' => 1, 'weight' => 40],
        ]]]);
    }

    #[Test]
    public function weighted_scores_must_be_whole_levels_on_the_scale(): void
    {
        $this->weightedBatch();
        $item = $this->item(['final_scores' => [3, 2]]);

        foreach ([[0, 2], [5, 2], [2.5, 2]] as $scores) {
            $this->page()->call('openItem', $item->id)->set('scores', $scores)->call('saveReview')->assertHasErrors('scores.0');
        }

        $this->assertEquals([3, 2], $item->fresh()->final_scores);
    }

    #[Test]
    public function weighted_totals_appear_out_of_the_total_weight(): void
    {
        $this->weightedBatch();
        $this->item(['status' => GradingItem::APPROVED, 'approved_at' => now(), 'final_scores' => [3, 2], 'ai_result' => [
            'criteria' => [
                ['score' => 3, 'reason' => 'a', 'evidence' => '', 'needs_review' => false],
                ['score' => 2, 'reason' => 'b', 'evidence' => '', 'needs_review' => false],
            ],
            'summary' => '',
            'injection_attempt' => false,
        ]]);

        // 3*60/4 + 2*40/4 = 65
        $this->page()->assertSee('Usulan AI 65/100')->assertSee('Nilai 65/100');
    }

    #[Test]
    public function weighted_criterion_shows_its_scale_and_weight(): void
    {
        $this->weightedBatch();
        $item = $this->item(['final_scores' => [3, 2]]);

        $this->page()->call('openItem', $item->id)->assertSee('Skor final (1–4)')->assertSee('bobot 60');
    }

    private function carousel(int $images = 3): GradingItem
    {
        $files = [];

        for ($i = 1; $i <= $images; $i++) {
            Storage::disk('local')->put("grading/1/s{$i}.png", 'PNG');
            $files[] = ['path' => "grading/1/s{$i}.png", 'name' => "s{$i}.png", 'mime' => 'image/png'];
        }

        return $this->item(['type' => 'image', 'files' => $files, 'extracted_text' => null]);
    }

    #[Test]
    public function every_image_is_shown_and_the_list_counts_them(): void
    {
        $item = $this->carousel();

        $page = $this->page()->assertSee('3 gambar')->call('openItem', $item->id);

        foreach ([0, 1, 2] as $index) {
            $page->assertSeeHtml(route('chat.grading.file', ['item' => $item, 'index' => $index]));
        }
    }

    #[Test]
    public function detail_panel_offers_adding_an_image(): void
    {
        $item = $this->carousel();

        $this->page()->call('openItem', $item->id)->assertSeeHtml(route('chat.grading.items.images', $item));
    }

    #[Test]
    public function removing_an_image_deletes_it_and_queues_a_regrade(): void
    {
        $item = $this->carousel();

        $this->page()->call('openItem', $item->id)->call('removeImage', $item->id, 1)->assertDispatched('grading-run');

        $item->refresh();

        $this->assertSame(['s1.png', 's3.png'], array_column($item->files, 'name'));
        $this->assertSame(GradingItem::QUEUED, $item->status);
        $this->assertEquals([7, 15], $item->final_scores);
        Storage::disk('local')->assertMissing('grading/1/s2.png');
    }

    #[Test]
    public function the_last_image_cannot_be_removed(): void
    {
        $item = $this->carousel(1);

        $this->page()->call('openItem', $item->id)->call('removeImage', $item->id, 0);

        $this->assertCount(1, $item->fresh()->files);
        Storage::disk('local')->assertExists('grading/1/s1.png');
    }

    #[Test]
    public function removing_an_unknown_image_or_from_a_video_does_nothing(): void
    {
        $item = $this->carousel(2);
        $video = $this->item(['type' => 'video', 'files' => [['path' => 'grading/1/s1.png', 'at' => 0], ['path' => 'grading/1/s2.png', 'at' => 1]], 'extracted_text' => null]);

        $this->page()->call('removeImage', $item->id, 9)->call('removeImage', $video->id, 0);

        $this->assertCount(2, $item->fresh()->files);
        $this->assertCount(2, $video->fresh()->files);
    }

    #[Test]
    public function a_submission_being_graded_says_so_and_keeps_refreshing(): void
    {
        $item = $this->item(['status' => GradingItem::PROCESSING]);

        $this->page()
            ->call('openItem', $item->id)
            ->assertSee('AI sedang menilai tugas ini')
            ->assertSee('Sedang dinilai…')
            ->assertSeeHtml('wire:poll.5s');
    }

    #[Test]
    public function a_finished_submission_stops_refreshing_and_offers_a_regrade(): void
    {
        $item = $this->item();

        $this->page()
            ->call('openItem', $item->id)
            ->assertDontSee('AI sedang menilai tugas ini')
            ->assertSee('Nilai ulang dengan AI')
            ->assertDontSeeHtml('wire:poll.5s');
    }

    #[Test]
    public function the_task_list_has_a_place_for_upload_progress(): void
    {
        $this->page()->assertSeeHtmlInOrder(['id="results-heading"', 'data-grading-progress', 'data-grading-pending', 'chat-grading__rows']);
    }

    #[Test]
    public function a_transcript_cannot_requeue_a_submission_being_graded(): void
    {
        // Every click used to queue another paid grading on top of the running one.
        $item = $this->item(['type' => 'video', 'status' => GradingItem::PROCESSING, 'transcript' => 'Lama', 'extracted_text' => null]);

        $this->page()
            ->call('openItem', $item->id)
            ->set('transcriptDraft', 'Baru')
            ->call('saveTranscript')
            ->assertHasErrors('transcriptDraft')
            ->assertNotDispatched('grading-run');

        $this->assertSame(GradingItem::PROCESSING, $item->fresh()->status);
        $this->assertSame('Lama', $item->fresh()->transcript);
    }

    #[Test]
    public function grading_without_a_transcript_cannot_requeue_a_submission_being_graded(): void
    {
        $item = $this->item(['type' => 'video', 'status' => GradingItem::PROCESSING, 'extracted_text' => null]);

        $this->page()->call('gradeWithoutTranscript', $item->id)->assertNotDispatched('grading-run');

        $this->assertSame(GradingItem::PROCESSING, $item->fresh()->status);
    }

    private function fakeNote(string $note): void
    {
        SiteSetting::set('ai_base_url', 'https://ai.test/v1', 'text', 'ai');
        AiClient::setApiKey('sk-TEST');
        Http::fake(['ai.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['note' => $note])]]]])]);
    }

    #[Test]
    public function an_ai_note_fills_the_field_and_saves_nothing(): void
    {
        $this->fakeNote('Argumen sudah baik, perbaiki tata bahasa.');
        $item = $this->item(['status' => GradingItem::READY]);

        $this->page()
            ->call('openItem', $item->id)
            ->set('scores', [9, 12])
            ->call('generateNote')
            ->assertHasNoErrors()
            ->assertSet('note', 'Argumen sudah baik, perbaiki tata bahasa.');

        $item->refresh();

        $this->assertNull($item->lecturer_note);
        $this->assertEquals([7, 15], $item->final_scores);
        $this->assertSame(GradingItem::READY, $item->status);
        $this->assertNull($item->approved_at);
    }

    #[Test]
    public function an_ai_note_needs_every_score_first(): void
    {
        $this->fakeNote('Tidak boleh terkirim.');
        $item = $this->item();

        $this->page()
            ->call('openItem', $item->id)
            ->set('scores', [9, null])
            ->call('generateNote')
            ->assertHasErrors(['note', 'scores.1'])
            ->assertSet('note', '');

        Http::assertNothingSent();
    }

    #[Test]
    public function a_failed_ai_note_keeps_what_the_lecturer_wrote(): void
    {
        SiteSetting::set('ai_base_url', 'https://ai.test/v1', 'text', 'ai');
        AiClient::setApiKey('sk-TEST');
        Http::fake(['ai.test/*' => Http::response(['error' => ['message' => 'Sibuk']], 500)]);
        $item = $this->item();

        $this->page()
            ->call('openItem', $item->id)
            ->set('note', 'Catatan saya')
            ->call('generateNote')
            ->assertHasErrors('note')
            ->assertSet('note', 'Catatan saya');
    }

    #[Test]
    public function removing_an_image_from_another_users_submission_is_404(): void
    {
        $other = ChatUser::create(['name' => 'Rina', 'pin' => Hash::make('654321')]);
        $foreign = $this->item(['type' => 'image', 'files' => [['path' => 'x', 'name' => 'x', 'mime' => 'image/png']]], $this->makeBatch($other));

        $this->assertThrows(fn () => $this->page()->call('removeImage', $foreign->id, 0), ModelNotFoundException::class);
    }
}
