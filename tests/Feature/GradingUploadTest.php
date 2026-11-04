<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\ChatPin;
use App\Models\AiModel;
use App\Models\ChatUser;
use App\Models\GradingBatch;
use App\Models\GradingItem;
use App\Support\UploadLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GradingUploadTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private ChatUser $user;

    private GradingBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        AiModel::create(['model_id' => 'vision', 'label' => 'Vision', 'is_enabled' => true, 'supports_vision' => true]);
        AiModel::create(['model_id' => 'text-only', 'label' => 'Text', 'is_enabled' => true]);

        $this->user = ChatUser::create(['name' => 'Ayu', 'pin' => Hash::make('123456')]);
        $this->batch = $this->makeBatch($this->user);
    }

    private function makeBatch(ChatUser $user, string $model = 'vision'): GradingBatch
    {
        return $user->gradingBatches()->create([
            'name' => 'Tugas',
            'model' => $model,
            'rubric_snapshot' => ['name' => 'R', 'guidance' => '', 'criteria' => [['name' => 'Isi', 'description' => '', 'max_score' => 10]]],
        ]);
    }

    /** A real file on disk, so the type is read from its content, not its name. */
    private function realFile(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function png(string $name = 'Budi Santoso.png'): UploadedFile
    {
        return $this->realFile($name, base64_decode(self::PNG));
    }

    private function upload(UploadedFile $file, ?GradingBatch $batch = null, ?ChatUser $as = null)
    {
        return $this->withCookie(ChatPin::COOKIE, ChatPin::cookieValue($as ?? $this->user))
            ->post(route('chat.grading.items.store', $batch ?? $this->batch), ['file' => $file]);
    }

    #[Test]
    public function upload_requires_the_pin(): void
    {
        $this->post(route('chat.grading.items.store', $this->batch), ['file' => $this->png()])
            ->assertRedirect(route('chat.pin'));
    }

    #[Test]
    public function image_upload_creates_a_queued_item_named_from_the_file(): void
    {
        $this->upload($this->png())
            ->assertOk()
            ->assertJson(['student_name' => 'Budi Santoso', 'status' => GradingItem::QUEUED]);

        $item = GradingItem::firstOrFail();

        $this->assertSame('image', $item->type);
        $this->assertSame('image/png', $item->files[0]['mime']);
        $this->assertStringStartsWith("grading/{$this->user->id}/", $item->files[0]['path']);
        Storage::disk('local')->assertExists($item->files[0]['path']);
    }

    #[Test]
    public function docx_upload_extracts_text(): void
    {
        $this->upload($this->realFile('Citra.docx', file_get_contents(__DIR__.'/../fixtures/sample.docx')))->assertOk();

        $item = GradingItem::firstOrFail();

        $this->assertSame('document', $item->type);
        $this->assertSame(GradingItem::QUEUED, $item->status);
        $this->assertStringContainsString('Halo dunia', $item->extracted_text);
    }

    #[Test]
    public function pdf_without_text_becomes_failed_with_a_message(): void
    {
        $this->upload($this->realFile('Scan.pdf', "%PDF-1.4\n%%EOF\n"))->assertOk();

        $item = GradingItem::firstOrFail();

        $this->assertSame(GradingItem::FAILED, $item->status);
        $this->assertStringContainsString('tidak berisi teks', $item->error);
    }

    #[Test]
    public function executable_and_oversize_files_are_rejected(): void
    {
        $this->upload(UploadedFile::fake()->create('virus.exe', 10))->assertStatus(422)->assertJsonStructure(['message']);
        $this->upload(UploadedFile::fake()->create('besar.png', 11 * 1024, 'image/png'))->assertStatus(422);

        $this->assertSame(0, GradingItem::count());
    }

    #[Test]
    public function file_renamed_to_jpg_with_other_content_is_rejected(): void
    {
        $this->upload($this->realFile('tugas.jpg', '<?php echo "bukan gambar";'))->assertStatus(422);

        $this->assertSame(0, GradingItem::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    #[Test]
    public function image_upload_requires_a_vision_model(): void
    {
        $batch = $this->makeBatch($this->user, 'text-only');

        $this->upload($this->png(), $batch)->assertStatus(422)->assertJsonFragment(['message' => 'Model sesi ini tidak bisa membaca gambar. Buat sesi dengan model lain.']);
        $this->upload($this->realFile('Citra.docx', file_get_contents(__DIR__.'/../fixtures/sample.docx')), $batch)->assertOk();
    }

    #[Test]
    public function batch_stops_at_one_hundred_items(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->batch->items()->create(['student_name' => "M{$i}", 'type' => 'image', 'original_name' => "m{$i}.png", 'status' => GradingItem::QUEUED]);
        }

        $this->upload($this->png())->assertStatus(422);

        $this->assertSame(100, GradingItem::count());
    }

    #[Test]
    public function same_file_name_twice_keeps_both(): void
    {
        $this->upload($this->png('Budi.png'))->assertOk();
        $this->upload($this->png('Budi.png'))->assertOk()->assertJson(['student_name' => 'Budi (2)']);
        $this->upload($this->png('Budi.png'))->assertOk()->assertJson(['student_name' => 'Budi (3)']);

        [$first, $second] = GradingItem::orderBy('id')->take(2)->get();

        $this->assertNotSame($first->files[0]['path'], $second->files[0]['path']);
    }

    #[Test]
    public function uploading_to_another_users_batch_is_404(): void
    {
        $other = ChatUser::create(['name' => 'Rina', 'pin' => Hash::make('654321')]);

        $this->upload($this->png(), $this->makeBatch($other))->assertNotFound();
        $this->assertSame(0, GradingItem::count());
    }

    private function storedItem(): GradingItem
    {
        Storage::disk('local')->put('grading/1/budi.png', 'PNGDATA');

        return $this->batch->items()->create([
            'student_name' => 'Budi',
            'type' => 'image',
            'original_name' => 'budi.png',
            'status' => GradingItem::QUEUED,
            'files' => [['path' => 'grading/1/budi.png', 'name' => 'budi.png', 'mime' => 'image/png']],
        ]);
    }

    #[Test]
    public function another_users_file_is_404(): void
    {
        $item = $this->storedItem();
        $other = ChatUser::create(['name' => 'Rina', 'pin' => Hash::make('654321')]);

        $this->withCookie(ChatPin::COOKIE, ChatPin::cookieValue($other))
            ->get(route('chat.grading.file', ['item' => $item, 'index' => 0]))
            ->assertNotFound();
    }

    #[Test]
    public function own_file_is_served_with_nosniff(): void
    {
        $item = $this->storedItem();

        $response = $this->withCookie(ChatPin::COOKIE, ChatPin::cookieValue($this->user))
            ->get(route('chat.grading.file', ['item' => $item, 'index' => 0]));

        $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame('PNGDATA', $response->streamedContent());
    }

    #[Test]
    public function unknown_file_index_is_404(): void
    {
        $item = $this->storedItem();

        $this->withCookie(ChatPin::COOKIE, ChatPin::cookieValue($this->user))
            ->get(route('chat.grading.file', ['item' => $item, 'index' => 9]))
            ->assertNotFound();
    }

    #[Test]
    public function batch_page_exposes_the_upload_limit(): void
    {
        $this->withCookie(ChatPin::COOKIE, ChatPin::cookieValue($this->user))
            ->get(route('chat.grading.batch', $this->batch))
            ->assertOk()
            ->assertSee('data-upload-limit', false)
            ->assertSee(route('chat.grading.items.store', $this->batch), false);
    }

    #[Test]
    public function batch_page_exposes_the_hosts_file_count_limit(): void
    {
        $this->withCookie(ChatPin::COOKIE, ChatPin::cookieValue($this->user))
            ->get(route('chat.grading.batch', $this->batch))
            ->assertOk()
            ->assertSee('data-max-files="'.UploadLimits::files().'"', false);
    }

    #[Test]
    public function numbered_images_join_one_submission_in_number_order(): void
    {
        $this->upload($this->png('Group 1 - 2.png'))->assertOk()->assertJson(['student_name' => 'Group 1']);
        $this->upload($this->png('Group 1 - 10.png'))->assertOk();
        $this->upload($this->png('Group 1 - 1.png'))->assertOk();

        $item = GradingItem::firstOrFail();

        $this->assertSame(1, GradingItem::count());
        $this->assertSame('Group 1', $item->student_name);
        $this->assertSame(['Group 1 - 1.png', 'Group 1 - 2.png', 'Group 1 - 10.png'], array_column($item->files, 'name'));
    }

    #[Test]
    public function the_plain_name_joins_its_numbered_images_in_either_order(): void
    {
        $this->upload($this->png('Group 1 - 2.png'))->assertOk();
        $this->upload($this->png('Group 1.png'))->assertOk();
        $this->upload($this->png('Group 2.png'))->assertOk();
        $this->upload($this->png('Group 2 - 2.png'))->assertOk();

        $this->assertSame(2, GradingItem::count());
        $this->assertSame(['Group 1.png', 'Group 1 - 2.png'], array_column(GradingItem::where('student_name', 'Group 1')->first()->files, 'name'));
        $this->assertSame(['Group 2.png', 'Group 2 - 2.png'], array_column(GradingItem::where('student_name', 'Group 2')->first()->files, 'name'));
    }

    #[Test]
    public function similar_names_without_the_spaced_dash_stay_separate(): void
    {
        foreach (['Group-1.png', 'Group-2.png', 'Group_1.png', 'Group_2.png', 'Group 1.png', 'Group 10.png', 'PROJECT_NAMA_2223000001.png'] as $name) {
            $this->upload($this->png($name))->assertOk();
        }

        $this->assertSame(7, GradingItem::count());
    }

    #[Test]
    public function a_long_number_after_the_dash_is_a_nim_not_a_page(): void
    {
        $this->upload($this->png('Ayu - 1000000001.png'))->assertOk();
        $this->upload($this->png('Budi - 1000000002.png'))->assertOk();

        $this->assertSame(['Ayu - 1000000001', 'Budi - 1000000002'], GradingItem::orderBy('id')->pluck('student_name')->all());
    }

    #[Test]
    public function uploading_the_same_numbered_image_twice_is_rejected(): void
    {
        $this->upload($this->png('Group 1 - 1.png'))->assertOk();
        $this->upload($this->png('Group 1 - 1.png'))->assertStatus(422)->assertJsonStructure(['message']);

        $this->assertCount(1, GradingItem::firstOrFail()->files);
    }

    #[Test]
    public function one_submission_stops_at_ten_images(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->upload($this->png("Group 1 - {$i}.png"))->assertOk();
        }

        $this->upload($this->png('Group 1 - 11.png'))->assertStatus(422);

        $this->assertCount(10, GradingItem::firstOrFail()->files);
    }

    #[Test]
    public function joining_images_does_not_count_against_the_batch_limit(): void
    {
        $this->upload($this->png('Group 1 - 1.png'))->assertOk();

        for ($i = 0; $i < 99; $i++) {
            $this->batch->items()->create(['student_name' => "M{$i}", 'type' => 'image', 'original_name' => "m{$i}.png", 'status' => GradingItem::QUEUED]);
        }

        $this->upload($this->png('Group 1 - 2.png'))->assertOk();
    }

    #[Test]
    public function adding_an_image_to_a_graded_submission_queues_a_regrade_and_keeps_the_lecturers_scores(): void
    {
        $this->upload($this->png('Group 1 - 1.png'))->assertOk();

        $item = GradingItem::firstOrFail();
        $item->update(['status' => GradingItem::READY, 'final_scores' => [5], 'lecturer_note' => 'Catatan']);

        $this->upload($this->png('Group 1 - 2.png'))->assertOk();

        $item->refresh();

        $this->assertSame(GradingItem::QUEUED, $item->status);
        $this->assertEquals([5], $item->final_scores);
        $this->assertSame('Catatan', $item->lecturer_note);
    }

    #[Test]
    public function a_submission_being_graded_refuses_more_images(): void
    {
        $this->upload($this->png('Group 1 - 1.png'))->assertOk();
        GradingItem::firstOrFail()->update(['status' => GradingItem::PROCESSING]);

        $this->upload($this->png('Group 1 - 2.png'))->assertStatus(422);

        $this->assertCount(1, GradingItem::firstOrFail()->files);
    }

    private function addImage(GradingItem $item, UploadedFile $file, ?ChatUser $as = null)
    {
        return $this->withCookie(ChatPin::COOKIE, ChatPin::cookieValue($as ?? $this->user))
            ->post(route('chat.grading.items.images', $item), ['file' => $file]);
    }

    #[Test]
    public function an_image_can_be_added_from_the_detail_panel_whatever_its_name(): void
    {
        $this->upload($this->png('Group 1.png'))->assertOk();
        $item = GradingItem::firstOrFail();

        $this->addImage($item, $this->png('slide kedua.png'))->assertOk();

        $this->assertSame(['Group 1.png', 'slide kedua.png'], array_column($item->fresh()->files, 'name'));
    }

    #[Test]
    public function only_images_can_be_added_to_a_submission(): void
    {
        $this->upload($this->png('Group 1.png'))->assertOk();
        $item = GradingItem::firstOrFail();

        $this->addImage($item, $this->realFile('Esai.docx', file_get_contents(__DIR__.'/../fixtures/sample.docx')))->assertStatus(422);

        $essay = $this->batch->items()->create(['student_name' => 'Esai', 'type' => 'document', 'original_name' => 'e.docx', 'status' => GradingItem::READY]);

        $this->addImage($essay, $this->png('x.png'))->assertStatus(422);
    }

    #[Test]
    public function adding_an_image_to_another_users_submission_is_404(): void
    {
        $this->upload($this->png('Group 1.png'))->assertOk();
        $other = ChatUser::create(['name' => 'Rina', 'pin' => Hash::make('654321')]);

        $this->addImage(GradingItem::firstOrFail(), $this->png('x.png'), $other)->assertNotFound();

        $this->assertCount(1, GradingItem::firstOrFail()->files);
    }
}
