<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\ChatPin;
use App\Livewire\Chat\Grading\Index;
use App\Models\AiModel;
use App\Models\ChatMessage;
use App\Models\ChatUser;
use App\Models\GradingBatch;
use App\Models\GradingItem;
use App\Models\GradingRubric;
use App\Models\SiteSetting;
use App\Services\AiClient;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GradingRubricTest extends TestCase
{
    use RefreshDatabase;

    private ChatUser $user;

    private ChatUser $other;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        SiteSetting::set('ai_base_url', 'https://ai.test/v1', 'text', 'ai');
        AiClient::setApiKey('sk-TEST');

        $this->user = ChatUser::create(['name' => 'Ayu', 'pin' => Hash::make('123456')]);
        $this->other = ChatUser::create(['name' => 'Rina', 'pin' => Hash::make('654321')]);

        AiModel::create(['model_id' => 'chat-basic', 'label' => 'Chat', 'is_enabled' => true, 'is_default' => true]);
    }

    private function page(): Testable
    {
        app()->instance('chatUser', $this->user);

        return Livewire::test(Index::class);
    }

    private function asUser(ChatUser $user): static
    {
        return $this->withCookie(ChatPin::COOKIE, ChatPin::cookieValue($user));
    }

    private function rubric(?ChatUser $owner = null): GradingRubric
    {
        return ($owner ?? $this->user)->rubrics()->create([
            'name' => 'Rubrik Esai',
            'guidance' => 'Utamakan argumen',
            'criteria' => [['name' => 'Isi', 'description' => 'Jelas', 'max_score' => 10]],
        ]);
    }

    private function batch(?ChatUser $owner = null): GradingBatch
    {
        return ($owner ?? $this->user)->gradingBatches()->create([
            'name' => 'Tugas 1',
            'model' => 'chat-basic',
            'rubric_snapshot' => ['name' => 'R', 'guidance' => '', 'criteria' => [['name' => 'Isi', 'description' => '', 'max_score' => 10]]],
        ]);
    }

    private function fakeDraft(array $criteria = [['name' => 'Isi', 'description' => 'Jelas', 'max_score' => 40, 'needs_review' => false]]): void
    {
        Http::fake(['ai.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
            'name' => 'Rubrik Impor',
            'guidance' => 'Panduan impor',
            'criteria' => $criteria,
        ])]]]])]);
    }

    private function fillForm(Testable $page): Testable
    {
        return $page->set('name', 'Rubrik Baru')
            ->set('criteria', [['name' => 'Isi', 'description' => 'Jelas', 'max_score' => 25, 'needs_review' => false]]);
    }

    #[Test]
    public function grading_page_is_noindex_and_pin_gated(): void
    {
        $this->get('/chat/penilaian')->assertRedirect(route('chat.pin'));

        $this->asUser($this->user)->get('/chat/penilaian')
            ->assertOk()
            ->assertSee('noindex', false)
            ->assertSee('dikirim ke penyedia AI');
    }

    #[Test]
    public function chat_sidebar_links_to_grading(): void
    {
        $this->asUser($this->user)->get('/chat')
            ->assertOk()
            ->assertSee(route('chat.grading'));
    }

    #[Test]
    public function saving_a_rubric_stores_it_for_the_signed_in_user(): void
    {
        $this->fillForm($this->page())->call('saveRubric')->assertHasNoErrors();

        $rubric = GradingRubric::firstOrFail();

        $this->assertSame($this->user->id, $rubric->chat_user_id);
        $this->assertEquals([['name' => 'Isi', 'description' => 'Jelas', 'max_score' => 25, 'human_only' => false]], $rubric->criteria);
    }

    #[Test]
    public function save_rubric_requires_a_criterion_and_positive_max_score(): void
    {
        $this->page()->set('name', 'Rubrik')->set('criteria', [])
            ->call('saveRubric')->assertHasErrors('criteria');

        $this->page()->set('name', 'Rubrik')
            ->set('criteria', [['name' => 'Isi', 'description' => '', 'max_score' => 0, 'needs_review' => false]])
            ->call('saveRubric')->assertHasErrors('criteria.0.max_score');

        $this->assertSame(0, GradingRubric::count());
    }

    #[Test]
    public function import_from_text_fills_the_form_without_saving(): void
    {
        $this->fakeDraft();

        $this->page()
            ->set('importText', 'Isi jelas, skor 40')
            ->call('importFromText')
            ->assertHasNoErrors()
            ->assertSet('name', 'Rubrik Impor')
            ->assertSet('guidance', 'Panduan impor')
            ->assertSet('criteria', [['name' => 'Isi', 'description' => 'Jelas', 'max_score' => 40.0, 'needs_review' => false]]);

        $this->assertSame(0, GradingRubric::count());
    }

    #[Test]
    public function import_from_document_fills_the_form_without_saving(): void
    {
        $this->fakeDraft();

        $this->page()
            ->set('importFile', UploadedFile::fake()->createWithContent('rubrik.docx', file_get_contents(__DIR__.'/../fixtures/sample.docx')))
            ->call('importFromDocument')
            ->assertHasNoErrors()
            ->assertSet('name', 'Rubrik Impor');

        Http::assertSent(fn (Request $request) => str_contains(json_encode($request->data()), 'Halo dunia'));
        $this->assertSame(0, GradingRubric::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    #[Test]
    public function import_from_document_rejects_other_file_types(): void
    {
        Http::fake();

        $this->page()
            ->set('importFile', UploadedFile::fake()->create('rubrik.exe', 10))
            ->call('importFromDocument')
            ->assertHasErrors('importFile');

        Http::assertNothingSent();
    }

    #[Test]
    public function pdf_without_text_shows_a_readable_error(): void
    {
        Http::fake();

        $this->page()
            ->set('importFile', UploadedFile::fake()->create('scan.pdf', 10, 'application/pdf'))
            ->call('importFromDocument')
            ->assertHasErrors('importFile');

        Http::assertNothingSent();
    }

    private function assistantMessage(ChatUser $owner, array $attributes = []): ChatMessage
    {
        return $owner->conversations()->create(['title' => 'Diskusi'])->messages()->create($attributes + [
            'role' => 'assistant',
            'content' => 'Kriteria: Isi, skor 40',
        ]);
    }

    #[Test]
    public function import_from_message_uses_the_answer_text(): void
    {
        $this->fakeDraft();
        $message = $this->assistantMessage($this->user);

        app()->instance('chatUser', $this->user);

        Livewire::withQueryParams(['dari_pesan' => $message->id])
            ->test(Index::class)
            ->call('importFromMessage')
            ->assertSet('name', 'Rubrik Impor');

        Http::assertSent(fn (Request $request) => str_contains(json_encode($request->data()), 'Kriteria: Isi, skor 40'));
        $this->assertSame(0, GradingRubric::count());
    }

    #[Test]
    public function import_from_another_users_message_is_404(): void
    {
        $message = $this->assistantMessage($this->other);

        $this->asUser($this->user)
            ->get('/chat/penilaian?dari_pesan='.$message->id)
            ->assertNotFound();
    }

    #[Test]
    public function save_as_rubric_button_is_only_on_plain_answers(): void
    {
        $answer = $this->assistantMessage($this->user);
        $conversation = $answer->conversation;
        $error = $conversation->messages()->create(['role' => 'assistant', 'content' => 'Gagal', 'is_error' => true]);

        $response = $this->asUser($this->user)->get('/chat/'.$conversation->id)->assertOk();

        $response->assertSee('Simpan sebagai rubrik');
        $response->assertSee('dari_pesan='.$answer->id, false);
        $response->assertDontSee('dari_pesan='.$error->id, false);
    }

    #[Test]
    public function import_failure_shows_message_and_keeps_form_usable(): void
    {
        $this->fakeDraft([]);

        $page = $this->page()
            ->set('importText', 'resep nasi goreng')
            ->call('importFromText')
            ->assertHasErrors('importText');

        $this->fillForm($page)->call('saveRubric')->assertHasNoErrors();
        $this->assertSame(1, GradingRubric::count());
    }

    #[Test]
    public function invented_max_score_is_marked_perlu_dicek(): void
    {
        $this->fakeDraft([['name' => 'Isi', 'description' => '', 'max_score' => 10, 'needs_review' => true]]);

        $this->page()
            ->set('importText', 'Isi harus jelas')
            ->call('importFromText')
            ->assertSee('perlu dicek');
    }

    #[Test]
    public function editing_loads_and_updates_the_rubric(): void
    {
        $rubric = $this->rubric();

        $this->page()
            ->call('editRubric', $rubric->id)
            ->assertSet('name', 'Rubrik Esai')
            ->assertSet('guidance', 'Utamakan argumen')
            ->set('name', 'Rubrik Revisi')
            ->call('saveRubric')
            ->assertHasNoErrors();

        $this->assertSame('Rubrik Revisi', $rubric->fresh()->name);
        $this->assertSame(1, GradingRubric::count());
    }

    #[Test]
    public function other_users_rubrics_and_batches_are_404_or_hidden(): void
    {
        $rubric = $this->rubric($this->other);
        $batch = $this->batch($this->other);

        $this->assertThrows(fn () => $this->page()->call('editRubric', $rubric->id), ModelNotFoundException::class);
        $this->assertThrows(fn () => $this->page()->call('deleteRubric', $rubric->id), ModelNotFoundException::class);
        $this->assertThrows(fn () => $this->page()->call('deleteBatch', $batch->id), ModelNotFoundException::class);

        $this->page()->assertDontSee('Rubrik Esai')->assertDontSee('Tugas 1');
        $this->assertNotNull($rubric->fresh());
        $this->assertNotNull($batch->fresh());
    }

    #[Test]
    public function batch_cannot_be_created_from_another_users_rubric(): void
    {
        $rubric = $this->rubric($this->other);

        $this->assertThrows(fn () => $this->page()
            ->set('batchName', 'Tugas')
            ->set('batchRubricId', $rubric->id)
            ->set('batchModel', 'chat-basic')
            ->call('createBatch'), ModelNotFoundException::class);

        $this->assertSame(0, GradingBatch::count());
    }

    #[Test]
    public function create_batch_snapshots_the_rubric(): void
    {
        $rubric = $this->rubric();

        $this->page()
            ->set('batchName', 'Tugas 2')
            ->set('batchRubricId', $rubric->id)
            ->set('batchModel', 'chat-basic')
            ->call('createBatch')
            ->assertHasNoErrors()
            ->assertRedirect(route('chat.grading.batch', GradingBatch::firstOrFail()));

        $batch = GradingBatch::firstOrFail();

        $this->assertSame($this->user->id, $batch->chat_user_id);
        $this->assertSame('chat-basic', $batch->model);
        $this->assertSame('Utamakan argumen', $batch->rubric_snapshot['guidance']);
        $this->assertSame('Isi', $batch->rubric_snapshot['criteria'][0]['name']);
    }

    #[Test]
    public function editing_a_rubric_does_not_change_an_existing_batch(): void
    {
        $rubric = $this->rubric();

        $this->page()
            ->set('batchName', 'Tugas')
            ->set('batchRubricId', $rubric->id)
            ->set('batchModel', 'chat-basic')
            ->call('createBatch');

        $this->page()
            ->call('editRubric', $rubric->id)
            ->set('criteria', [['name' => 'Baru', 'description' => '', 'max_score' => 5, 'needs_review' => false]])
            ->call('saveRubric')
            ->assertHasNoErrors();

        $this->assertSame('Isi', GradingBatch::firstOrFail()->rubric_snapshot['criteria'][0]['name']);
    }

    #[Test]
    public function deleting_a_rubric_keeps_its_batch_usable(): void
    {
        $rubric = $this->rubric();

        $this->page()
            ->set('batchName', 'Tugas')
            ->set('batchRubricId', $rubric->id)
            ->set('batchModel', 'chat-basic')
            ->call('createBatch');

        $this->page()->call('deleteRubric', $rubric->id);

        $batch = GradingBatch::firstOrFail();

        $this->assertSame(0, GradingRubric::count());
        $this->assertSame(10.0, $batch->maxScore());
        $this->asUser($this->user)->get(route('chat.grading.batch', $batch))->assertOk();
    }

    #[Test]
    public function delete_batch_removes_its_files(): void
    {
        $batch = $this->batch();
        Storage::disk('local')->put('grading/1/a.jpg', 'JPG');
        $batch->items()->create([
            'student_name' => 'Budi',
            'type' => 'image',
            'original_name' => 'a.jpg',
            'status' => GradingItem::QUEUED,
            'files' => [['path' => 'grading/1/a.jpg', 'name' => 'a.jpg', 'mime' => 'image/jpeg']],
        ]);

        $this->page()->call('deleteBatch', $batch->id);

        Storage::disk('local')->assertMissing('grading/1/a.jpg');
        $this->assertSame(0, GradingBatch::count());
    }

    #[Test]
    public function transcribe_models_are_not_offered_for_batches(): void
    {
        AiModel::create(['model_id' => 'voice-model', 'label' => 'Voice', 'is_enabled' => true, 'is_transcribe' => true]);
        $rubric = $this->rubric();

        $this->page()
            ->assertDontSee('voice-model')
            ->set('batchName', 'Tugas')
            ->set('batchRubricId', $rubric->id)
            ->set('batchModel', 'voice-model')
            ->call('createBatch')
            ->assertHasErrors('batchModel');
    }

    #[Test]
    public function another_users_batch_page_is_404(): void
    {
        $batch = $this->batch($this->other);

        $this->asUser($this->user)->get(route('chat.grading.batch', $batch))->assertNotFound();
    }

    #[Test]
    public function human_only_flag_is_saved_with_the_rubric(): void
    {
        $this->page()
            ->set('name', 'Rubrik')
            ->set('criteria', [['name' => 'Pelafalan', 'description' => '', 'max_score' => 30, 'needs_review' => false, 'human_only' => true]])
            ->call('saveRubric')
            ->assertHasNoErrors();

        $this->assertTrue(GradingRubric::firstOrFail()->criteria[0]['human_only']);
    }

    #[Test]
    public function weighted_criterion_is_saved_with_its_scale(): void
    {
        $this->page()
            ->set('name', 'Rubrik')
            ->set('criteria', [['name' => 'Relevansi', 'description' => '', 'max_score' => 4, 'min_score' => 1, 'weight' => 20, 'needs_review' => false, 'human_only' => false]])
            ->call('saveRubric')
            ->assertHasNoErrors();

        $criterion = GradingRubric::firstOrFail()->criteria[0];

        $this->assertEquals(1, $criterion['min_score']);
        $this->assertEquals(20, $criterion['weight']);
    }

    #[Test]
    public function minimum_must_stay_below_the_maximum_and_weight_positive(): void
    {
        $base = ['name' => 'Relevansi', 'description' => '', 'max_score' => 4, 'needs_review' => false, 'human_only' => false];

        $this->page()->set('name', 'Rubrik')->set('criteria', [$base + ['min_score' => 4]])
            ->call('saveRubric')->assertHasErrors('criteria.0.min_score');

        $this->page()->set('name', 'Rubrik')->set('criteria', [$base + ['weight' => 0]])
            ->call('saveRubric')->assertHasErrors('criteria.0.weight');

        $this->assertSame(0, GradingRubric::count());
    }

    #[Test]
    public function plain_criterion_stores_no_scale_fields(): void
    {
        $this->page()
            ->set('name', 'Rubrik')
            ->set('criteria', [['name' => 'Isi', 'description' => '', 'max_score' => 10, 'min_score' => '', 'weight' => '', 'needs_review' => false, 'human_only' => false]])
            ->call('saveRubric')
            ->assertHasNoErrors();

        $this->assertArrayNotHasKey('weight', GradingRubric::firstOrFail()->criteria[0]);
        $this->assertArrayNotHasKey('min_score', GradingRubric::firstOrFail()->criteria[0]);
    }
}
