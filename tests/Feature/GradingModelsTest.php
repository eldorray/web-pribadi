<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Admin\ChatAi\Index;
use App\Models\AiModel;
use App\Models\ChatUser;
use App\Models\GradingBatch;
use App\Models\GradingItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GradingModelsTest extends TestCase
{
    use RefreshDatabase;

    private function batch(array $maxScores = [10, 20]): GradingBatch
    {
        $user = ChatUser::create(['name' => 'Ayu', 'pin' => Hash::make('123456')]);

        return $user->gradingBatches()->create([
            'name' => 'Tugas 1',
            'model' => 'chat-basic',
            'rubric_snapshot' => [
                'name' => 'Rubrik',
                'guidance' => '',
                'criteria' => array_map(
                    fn (int $max, int $i) => ['name' => "K{$i}", 'description' => '', 'max_score' => $max],
                    $maxScores,
                    array_keys($maxScores),
                ),
            ],
        ]);
    }

    private function item(GradingBatch $batch, array $attributes = []): GradingItem
    {
        return $batch->items()->create($attributes + [
            'student_name' => 'Budi',
            'type' => 'image',
            'original_name' => 'budi.jpg',
            'status' => GradingItem::QUEUED,
        ]);
    }

    #[Test]
    public function transcribe_models_are_not_chat_models(): void
    {
        AiModel::create(['model_id' => 'chat', 'label' => 'Chat', 'is_enabled' => true]);
        AiModel::create(['model_id' => 'voice', 'label' => 'Voice', 'is_enabled' => true, 'is_transcribe' => true]);

        $this->assertSame(['chat'], AiModel::chat()->pluck('model_id')->all());
        $this->assertSame('voice', AiModel::defaultTranscribe()?->model_id);
    }

    #[Test]
    public function disabled_transcribe_model_is_not_the_default(): void
    {
        AiModel::create(['model_id' => 'voice', 'label' => 'Voice', 'is_enabled' => false, 'is_transcribe' => true]);

        $this->assertNull(AiModel::defaultTranscribe());
    }

    #[Test]
    public function totals_are_computed_from_scores(): void
    {
        $batch = $this->batch([10, 20]);
        $item = $this->item($batch, ['final_scores' => [5, 15]]);

        $this->assertSame(30.0, $batch->maxScore());
        $this->assertSame(20.0, $item->finalTotal());
        $this->assertSame(66.67, $item->finalScaled());
    }

    #[Test]
    public function final_total_is_null_until_every_score_is_set(): void
    {
        $item = $this->item($this->batch(), ['final_scores' => [5, null]]);

        $this->assertNull($item->finalTotal());
        $this->assertNull($item->finalScaled());
    }

    #[Test]
    public function ai_total_sums_the_proposed_scores(): void
    {
        $item = $this->item($this->batch(), ['ai_result' => [
            'criteria' => [
                ['score' => 7, 'reason' => '', 'evidence' => '', 'needs_review' => false],
                ['score' => 11.5, 'reason' => '', 'evidence' => '', 'needs_review' => true],
            ],
            'summary' => '',
            'injection_attempt' => false,
        ]]);

        $this->assertSame(18.5, $item->aiTotal());
    }

    #[Test]
    public function stale_processing_items_are_released(): void
    {
        $batch = $this->batch();
        $stale = $this->item($batch, ['status' => GradingItem::PROCESSING]);
        $fresh = $this->item($batch, ['status' => GradingItem::PROCESSING]);

        DB::table('grading_items')->where('id', $stale->id)->update(['updated_at' => now()->subMinutes(11)]);
        DB::table('grading_items')->where('id', $fresh->id)->update(['updated_at' => now()->subMinutes(9)]);

        $this->assertSame(1, $batch->releaseStaleItems());
        $this->assertSame(GradingItem::QUEUED, $stale->fresh()->status);
        $this->assertSame(GradingItem::PROCESSING, $fresh->fresh()->status);
    }

    #[Test]
    public function deleting_a_chat_user_removes_grading_files(): void
    {
        Storage::fake('local');

        $batch = $this->batch();
        $path = "grading/{$batch->chat_user_id}/budi.jpg";
        Storage::disk('local')->put($path, 'JPG');
        $this->item($batch, ['files' => [['path' => $path, 'name' => 'budi.jpg', 'mime' => 'image/jpeg']]]);

        Livewire::actingAs(User::factory()->create())
            ->test(Index::class)
            ->call('deleteUser', $batch->chat_user_id);

        Storage::disk('local')->assertMissing($path);
        $this->assertSame(0, GradingBatch::count());
    }

    #[Test]
    public function setting_default_transcribe_keeps_the_default_chat_model(): void
    {
        $chat = AiModel::create(['model_id' => 'chat', 'label' => 'Chat', 'is_enabled' => true, 'is_default' => true]);
        $voice = AiModel::create(['model_id' => 'voice', 'label' => 'Voice', 'is_enabled' => true, 'is_transcribe' => true]);

        Livewire::actingAs(User::factory()->create())
            ->test(Index::class)
            ->call('updateModel', $voice->id, 'is_default', true);

        $this->assertTrue($chat->fresh()->is_default);
        $this->assertTrue($voice->fresh()->is_default);
    }

    #[Test]
    public function transcribe_flag_can_be_set_from_admin(): void
    {
        $voice = AiModel::create(['model_id' => 'voice', 'label' => 'Voice', 'is_enabled' => true]);

        Livewire::actingAs(User::factory()->create())
            ->test(Index::class)
            ->call('updateModel', $voice->id, 'is_transcribe', true);

        $this->assertTrue($voice->fresh()->is_transcribe);
    }

    #[Test]
    public function transcribe_column_is_explained_and_comes_right_after_active(): void
    {
        AiModel::create(['model_id' => 'voice', 'label' => 'Voice', 'is_enabled' => true]);

        // Placed last it sat past the table's visible edge, so nobody found it.
        Livewire::actingAs(User::factory()->create())
            ->test(Index::class)
            ->assertSeeInOrder(['Aktif', 'Transkripsi', 'Default', 'Effort'])
            ->assertSee('model pengubah suara menjadi teks');
    }
}
