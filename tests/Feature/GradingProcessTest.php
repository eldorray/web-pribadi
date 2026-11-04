<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\ChatPin;
use App\Models\AiModel;
use App\Models\ChatUser;
use App\Models\GradingBatch;
use App\Models\GradingItem;
use App\Models\SiteSetting;
use App\Services\AiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GradingProcessTest extends TestCase
{
    use RefreshDatabase;

    private ChatUser $user;

    private GradingBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        SiteSetting::set('ai_base_url', 'https://ai.test/v1', 'text', 'ai');
        AiClient::setApiKey('sk-TEST');
        AiModel::create(['model_id' => 'vision', 'label' => 'Vision', 'is_enabled' => true, 'supports_vision' => true]);

        $this->user = ChatUser::create(['name' => 'Ayu', 'pin' => Hash::make('123456')]);
        $this->batch = $this->user->gradingBatches()->create([
            'name' => 'Tugas',
            'model' => 'vision',
            'rubric_snapshot' => ['name' => 'R', 'guidance' => '', 'criteria' => [
                ['name' => 'Isi', 'description' => '', 'max_score' => 10],
                ['name' => 'Bahasa', 'description' => '', 'max_score' => 20],
            ]],
        ]);
    }

    private function essay(array $attributes = []): GradingItem
    {
        return $this->batch->items()->create($attributes + [
            'student_name' => 'Budi',
            'type' => 'document',
            'original_name' => 'budi.docx',
            'status' => GradingItem::QUEUED,
            'extracted_text' => 'Esai',
        ]);
    }

    private function fakeGrades(): void
    {
        Http::fake(['ai.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
            'criteria' => [
                ['index' => 0, 'score' => 7, 'reason' => 'a', 'evidence' => 'b', 'needs_review' => false],
                ['index' => 1, 'score' => 15, 'reason' => 'c', 'evidence' => 'd', 'needs_review' => true],
            ],
            'summary' => 'Baik',
            'injection_attempt' => false,
        ])]]]])]);
    }

    private function grade(GradingItem $item, ?ChatUser $as = null)
    {
        return $this->withCookie(ChatPin::COOKIE, ChatPin::cookieValue($as ?? $this->user))
            ->post(route('chat.grading.items.grade', $item));
    }

    #[Test]
    public function grading_stores_result_and_prefills_final_scores(): void
    {
        $this->fakeGrades();
        $item = $this->essay();

        $this->grade($item)->assertOk()->assertJson(['id' => $item->id, 'status' => GradingItem::READY]);

        $item->refresh();

        $this->assertSame(GradingItem::READY, $item->status);
        $this->assertEquals([7, 15], $item->final_scores);
        $this->assertSame(22.0, $item->aiTotal());
        $this->assertNull($item->approved_at);
    }

    #[Test]
    public function failure_marks_failed_with_a_readable_message(): void
    {
        Http::fake(['ai.test/*' => Http::response(['error' => ['message' => 'kuota habis']], 429)]);
        $item = $this->essay();

        $this->grade($item)->assertOk()->assertJson(['status' => GradingItem::FAILED]);

        $this->assertStringContainsString('kuota habis', $item->fresh()->error);
    }

    #[Test]
    public function one_failure_leaves_the_next_item_gradable(): void
    {
        $first = $this->essay();
        $second = $this->essay(['student_name' => 'Citra']);

        Http::fakeSequence('ai.test/*')
            ->push('boom', 500)
            ->push(['choices' => [['message' => ['content' => json_encode([
                'criteria' => [
                    ['index' => 0, 'score' => 7],
                    ['index' => 1, 'score' => 15],
                ],
            ])]]]]);

        $this->grade($first)->assertOk()->assertJson(['status' => GradingItem::FAILED]);
        $this->grade($second)->assertOk()->assertJson(['status' => GradingItem::READY]);

        $this->assertSame(GradingItem::FAILED, $first->fresh()->status);
    }

    #[Test]
    public function second_grade_request_is_409_and_calls_the_provider_once(): void
    {
        Http::fake();
        $item = $this->essay(['status' => GradingItem::PROCESSING]);

        $this->grade($item)->assertStatus(409);

        Http::assertNothingSent();
        $this->assertSame(GradingItem::PROCESSING, $item->fresh()->status);
    }

    #[Test]
    public function disabled_model_fails_readably(): void
    {
        Http::fake();
        AiModel::where('model_id', 'vision')->update(['is_enabled' => false]);
        $item = $this->essay();

        $this->grade($item)->assertOk()->assertJson(['status' => GradingItem::FAILED]);

        Http::assertNothingSent();
        $this->assertStringContainsString('tidak lagi aktif', $item->fresh()->error);
    }

    #[Test]
    public function deleted_model_fails_readably(): void
    {
        Http::fake();
        AiModel::where('model_id', 'vision')->delete();
        $item = $this->essay();

        $this->grade($item)->assertOk()->assertJson(['status' => GradingItem::FAILED]);
    }

    #[Test]
    public function regrade_keeps_final_scores_note_and_approval(): void
    {
        $this->fakeGrades();
        $approvedAt = now()->subDay()->startOfSecond();
        $item = $this->essay([
            'final_scores' => [1, 2],
            'lecturer_note' => 'Catatan dosen',
            'approved_at' => $approvedAt,
        ]);

        $this->grade($item)->assertOk()->assertJson(['status' => GradingItem::APPROVED]);

        $item->refresh();

        $this->assertEquals([1, 2], $item->final_scores);
        $this->assertSame('Catatan dosen', $item->lecturer_note);
        $this->assertTrue($approvedAt->equalTo($item->approved_at));
        $this->assertSame(22.0, $item->aiTotal());
    }

    #[Test]
    public function failed_regrade_of_an_approved_item_stays_approved(): void
    {
        Http::fake(['ai.test/*' => Http::response('boom', 500)]);
        $item = $this->essay(['final_scores' => [1, 2], 'approved_at' => now()]);

        $this->grade($item)->assertOk()->assertJson(['status' => GradingItem::APPROVED]);

        $this->assertEquals([1, 2], $item->fresh()->final_scores);
        $this->assertNotNull($item->fresh()->error);
    }

    private function video(array $attributes): GradingItem
    {
        return $this->batch->items()->create($attributes + [
            'student_name' => 'Dewi',
            'type' => 'video',
            'original_name' => 'dewi.mp4',
            'status' => GradingItem::QUEUED,
            'files' => [],
            'duration_seconds' => 60,
        ]);
    }

    #[Test]
    public function video_with_failed_transcription_is_not_graded(): void
    {
        Http::fake();
        $item = $this->video(['transcript_status' => GradingItem::TRANSCRIPT_FAILED]);

        $this->grade($item)->assertOk()->assertJson(['status' => GradingItem::FAILED]);

        Http::assertNothingSent();
        $this->assertStringContainsString('Transkripsi belum tersedia', $item->fresh()->error);
        $this->assertNull($item->fresh()->ai_result);
    }

    #[Test]
    public function video_marked_skipped_is_graded(): void
    {
        $this->fakeGrades();
        $item = $this->video(['transcript_status' => GradingItem::TRANSCRIPT_SKIPPED]);

        $this->grade($item)->assertOk()->assertJson(['status' => GradingItem::READY]);
    }

    #[Test]
    public function successful_regrade_clears_an_old_error(): void
    {
        $this->fakeGrades();
        $item = $this->essay(['error' => 'Gagal sebelumnya']);

        $this->grade($item)->assertOk();

        $this->assertNull($item->fresh()->error);
    }

    #[Test]
    public function another_users_item_is_404(): void
    {
        Http::fake();
        $other = ChatUser::create(['name' => 'Rina', 'pin' => Hash::make('654321')]);
        $item = $this->essay();

        $this->grade($item, $other)->assertNotFound();

        Http::assertNothingSent();
        $this->assertSame(GradingItem::QUEUED, $item->fresh()->status);
    }

    #[Test]
    public function batch_page_lists_queued_items_for_the_browser_queue(): void
    {
        $item = $this->essay();
        $done = $this->essay(['student_name' => 'Citra', 'status' => GradingItem::READY]);

        $this->withCookie(ChatPin::COOKIE, ChatPin::cookieValue($this->user))
            ->get(route('chat.grading.batch', $this->batch))
            ->assertOk()
            ->assertSee('data-grade-url="'.route('chat.grading.items.grade', $item).'"', false)
            ->assertDontSee('data-grade-url="'.route('chat.grading.items.grade', $done).'"', false);
    }

    #[Test]
    public function opening_the_batch_page_releases_stuck_items(): void
    {
        $item = $this->essay(['status' => GradingItem::PROCESSING]);
        GradingItem::whereKey($item->id)->toBase()->update(['updated_at' => now()->subMinutes(11)]);

        $this->withCookie(ChatPin::COOKIE, ChatPin::cookieValue($this->user))
            ->get(route('chat.grading.batch', $this->batch))
            ->assertOk();

        $this->assertSame(GradingItem::QUEUED, $item->fresh()->status);
    }

    #[Test]
    public function scores_saved_while_grading_are_not_overwritten(): void
    {
        $item = $this->essay();

        Http::fake(['ai.test/*' => function () use ($item) {
            GradingItem::whereKey($item->id)->update(['final_scores' => json_encode([1, 2])]);

            return Http::response(['choices' => [['message' => ['content' => json_encode([
                'criteria' => [['index' => 0, 'score' => 7], ['index' => 1, 'score' => 15]],
            ])]]]]);
        }]);

        $this->grade($item)->assertOk();

        $this->assertEquals([1, 2], $item->fresh()->final_scores);
        $this->assertSame(22.0, $item->fresh()->aiTotal());
    }
}
