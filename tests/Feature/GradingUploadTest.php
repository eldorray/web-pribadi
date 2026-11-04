<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\ChatPin;
use App\Models\AiModel;
use App\Models\ChatUser;
use App\Models\GradingBatch;
use App\Models\GradingItem;
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
}
