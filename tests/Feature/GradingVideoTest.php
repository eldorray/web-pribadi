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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GradingVideoTest extends TestCase
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
        AiModel::create(['model_id' => 'text-only', 'label' => 'Text', 'is_enabled' => true]);
        AiModel::create(['model_id' => 'voice', 'label' => 'Voice', 'is_enabled' => true, 'is_transcribe' => true]);

        $this->user = ChatUser::create(['name' => 'Ayu', 'pin' => Hash::make('123456')]);
        $this->batch = $this->makeBatch('vision');
    }

    private function makeBatch(string $model): GradingBatch
    {
        return $this->user->gradingBatches()->create([
            'name' => 'Tugas',
            'model' => $model,
            'rubric_snapshot' => ['name' => 'R', 'guidance' => '', 'criteria' => [['name' => 'Isi', 'description' => '', 'max_score' => 10]]],
        ]);
    }

    private function as(?ChatUser $user = null): static
    {
        return $this->withCookie(ChatPin::COOKIE, ChatPin::cookieValue($user ?? $this->user));
    }

    private function createVideo(array $data = [], ?GradingBatch $batch = null)
    {
        return $this->as()->post(route('chat.grading.videos.store', $batch ?? $this->batch), $data + [
            'name' => 'Dewi.mp4',
            'duration_seconds' => 120,
            'has_audio' => 1,
        ]);
    }

    private function video(array $attributes = []): GradingItem
    {
        return $this->batch->items()->create($attributes + [
            'student_name' => 'Dewi',
            'type' => 'video',
            'original_name' => 'Dewi.mp4',
            'status' => GradingItem::PREPARING,
            'transcript_status' => GradingItem::TRANSCRIPT_PENDING,
            'duration_seconds' => 120,
        ]);
    }

    private function wav(): UploadedFile
    {
        return UploadedFile::fake()->create('chunk.wav', 10, 'audio/wav');
    }

    private function postAudio(GradingItem $item, int $offset, ?ChatUser $as = null)
    {
        return $this->as($as)->post(route('chat.grading.videos.audio', $item), [
            'audio' => $this->wav(),
            'offset_seconds' => $offset,
        ]);
    }

    #[Test]
    public function video_item_is_created_preparing_without_a_file_on_disk(): void
    {
        $this->createVideo()->assertOk()->assertJson(['student_name' => 'Dewi', 'status' => GradingItem::PREPARING]);

        $item = GradingItem::firstOrFail();

        $this->assertSame('video', $item->type);
        $this->assertSame(120, $item->duration_seconds);
        $this->assertSame(GradingItem::TRANSCRIPT_PENDING, $item->transcript_status);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    #[Test]
    public function video_over_twenty_minutes_is_rejected(): void
    {
        $this->createVideo(['duration_seconds' => 1201])->assertStatus(422)->assertJsonStructure(['message']);
        $this->createVideo(['duration_seconds' => 1200])->assertOk();
    }

    #[Test]
    public function video_requires_a_vision_model(): void
    {
        $this->createVideo([], $this->makeBatch('text-only'))->assertStatus(422);
    }

    #[Test]
    public function video_counts_toward_the_batch_limit(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->batch->items()->create(['student_name' => "M{$i}", 'type' => 'image', 'original_name' => "m{$i}.png", 'status' => GradingItem::QUEUED]);
        }

        $this->createVideo()->assertStatus(422);
    }

    #[Test]
    public function reselecting_a_preparing_video_reuses_the_item(): void
    {
        $this->createVideo()->assertOk();
        $first = GradingItem::firstOrFail();

        Storage::disk('local')->put('grading/1/old.jpg', 'JPG');
        $first->update(['files' => [['path' => 'grading/1/old.jpg', 'at' => 0]], 'transcript' => 'lama']);

        $this->createVideo()->assertOk()->assertJson(['id' => $first->id]);

        $this->assertSame(1, GradingItem::count());
        $this->assertNull($first->fresh()->transcript);
        $this->assertSame([], $first->fresh()->files);
        Storage::disk('local')->assertMissing('grading/1/old.jpg');
    }

    #[Test]
    public function same_name_as_a_finished_video_creates_a_new_item(): void
    {
        $this->video(['status' => GradingItem::READY]);

        $this->createVideo()->assertOk()->assertJson(['student_name' => 'Dewi (2)']);

        $this->assertSame(2, GradingItem::count());
    }

    #[Test]
    public function frames_are_stored_privately_with_their_times_and_capped_at_thirty(): void
    {
        $item = $this->video();

        $this->as()->post(route('chat.grading.videos.frames', $item), [
            'frames' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
            'at' => [4, 8],
        ])->assertOk();

        $files = $item->fresh()->files;

        $this->assertSame([4, 8], array_column($files, 'at'));
        $this->assertStringStartsWith("grading/{$this->user->id}/", $files[0]['path']);
        Storage::disk('local')->assertExists($files[1]['path']);

        $item->update(['files' => array_fill(0, 30, ['path' => 'x', 'at' => 0])]);

        $this->as()->post(route('chat.grading.videos.frames', $item), [
            'frames' => [UploadedFile::fake()->image('c.jpg')],
            'at' => [12],
        ])->assertStatus(422);
    }

    #[Test]
    public function frames_must_be_images(): void
    {
        $this->as()->post(route('chat.grading.videos.frames', $this->video()), [
            'frames' => [UploadedFile::fake()->create('a.exe', 10)],
            'at' => [1],
        ])->assertStatus(422);
    }

    #[Test]
    public function audio_chunks_append_timestamped_text_in_order(): void
    {
        Http::fakeSequence('ai.test/*')
            ->push(['text' => 'bagian satu'])
            ->push(['text' => 'bagian dua']);

        $item = $this->video();

        $this->postAudio($item, 0)->assertOk();
        $this->postAudio($item, 300)->assertOk();

        $this->assertSame("[00:00] bagian satu\n[05:00] bagian dua", $item->fresh()->transcript);
    }

    #[Test]
    public function first_chunk_clears_an_earlier_attempt(): void
    {
        Http::fake(['ai.test/*' => Http::response(['text' => 'baru'])]);

        $item = $this->video(['transcript' => '[00:00] lama']);

        $this->postAudio($item, 0)->assertOk();

        $this->assertSame('[00:00] baru', $item->fresh()->transcript);
    }

    #[Test]
    public function audio_temp_file_is_deleted_after_success_and_failure(): void
    {
        $item = $this->video();

        Http::fakeSequence('ai.test/*')->push(['text' => 'halo'])->push('boom', 500);

        $this->postAudio($item, 0)->assertOk();
        $this->postAudio($item, 300)->assertStatus(422);

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    #[Test]
    public function audio_uses_the_transcribe_model(): void
    {
        Http::fake(['ai.test/*' => Http::response(['text' => 'halo'])]);

        $this->postAudio($this->video(), 0)->assertOk();

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/audio/transcriptions')
            && collect($request->data())->contains(fn ($part) => $part['name'] === 'model' && $part['contents'] === 'voice'));
    }

    #[Test]
    public function missing_transcription_endpoint_marks_transcript_failed(): void
    {
        Http::fake(['ai.test/*' => Http::response('nope', 404)]);
        $item = $this->video();

        $this->postAudio($item, 0)->assertStatus(422)->assertJsonStructure(['message']);

        $this->assertSame(GradingItem::TRANSCRIPT_FAILED, $item->fresh()->transcript_status);
        $this->assertNotNull($item->fresh()->error);
    }

    #[Test]
    public function missing_transcribe_model_marks_transcript_failed(): void
    {
        Http::fake();
        AiModel::where('is_transcribe', true)->delete();
        $item = $this->video();

        $this->postAudio($item, 0)->assertStatus(422);

        Http::assertNothingSent();
        $this->assertSame(GradingItem::TRANSCRIPT_FAILED, $item->fresh()->transcript_status);
        $this->assertStringContainsString('model transkripsi', $item->fresh()->error);
    }

    #[Test]
    public function ready_queues_a_video_and_marks_transcript_done(): void
    {
        $item = $this->video(['transcript' => '[00:00] halo']);

        $this->as()->post(route('chat.grading.videos.ready', $item))->assertOk();

        $this->assertSame(GradingItem::QUEUED, $item->fresh()->status);
        $this->assertSame(GradingItem::TRANSCRIPT_DONE, $item->fresh()->transcript_status);
    }

    #[Test]
    public function ready_does_not_queue_a_failed_transcript(): void
    {
        $item = $this->video(['transcript_status' => GradingItem::TRANSCRIPT_FAILED, 'error' => 'gagal']);

        $this->as()->post(route('chat.grading.videos.ready', $item))->assertOk();

        $this->assertSame(GradingItem::FAILED, $item->fresh()->status);
        $this->assertSame('gagal', $item->fresh()->error);
    }

    #[Test]
    public function no_audio_video_is_queued_without_transcription(): void
    {
        Http::fake();

        $this->createVideo(['has_audio' => 0])->assertOk();

        $item = GradingItem::firstOrFail();

        $this->assertSame(GradingItem::TRANSCRIPT_NO_AUDIO, $item->transcript_status);

        $this->as()->post(route('chat.grading.videos.ready', $item))->assertOk();

        $this->assertSame(GradingItem::QUEUED, $item->fresh()->status);
        $this->assertSame(GradingItem::TRANSCRIPT_NO_AUDIO, $item->fresh()->transcript_status);
        Http::assertNothingSent();
    }

    #[Test]
    public function browser_decode_failure_can_be_reported(): void
    {
        $item = $this->video();

        $this->as()->post(route('chat.grading.videos.ready', $item), ['audio_failed' => 1])->assertOk();

        $this->assertSame(GradingItem::FAILED, $item->fresh()->status);
        $this->assertSame(GradingItem::TRANSCRIPT_FAILED, $item->fresh()->transcript_status);
        $this->assertStringContainsString('Suara video', $item->fresh()->error);
    }

    #[Test]
    public function another_users_item_is_404_on_every_route(): void
    {
        $other = ChatUser::create(['name' => 'Rina', 'pin' => Hash::make('654321')]);
        $item = $this->video();

        $this->as($other)->post(route('chat.grading.videos.store', $this->batch), ['name' => 'a.mp4', 'duration_seconds' => 1, 'has_audio' => 1])->assertNotFound();
        $this->as($other)->post(route('chat.grading.videos.frames', $item), ['frames' => [UploadedFile::fake()->image('a.jpg')], 'at' => [1]])->assertNotFound();
        $this->postAudio($item, 0, $other)->assertNotFound();
        $this->as($other)->post(route('chat.grading.videos.ready', $item))->assertNotFound();
    }
}
