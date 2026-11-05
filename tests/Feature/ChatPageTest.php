<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Chat\Page;
use App\Models\AiModel;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatUser;
use App\Models\SiteSetting;
use App\Services\AiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatPageTest extends TestCase
{
    use RefreshDatabase;

    private ChatUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        SiteSetting::set('ai_base_url', 'https://ai.test/v1', 'text', 'ai');
        SiteSetting::set('ai_streaming', '0', 'text', 'ai');
        AiClient::setApiKey('sk-TEST');

        $this->user = ChatUser::create(['name' => 'Ayu', 'pin' => Hash::make('123456')]);

        AiModel::create([
            'model_id' => 'chat-basic',
            'label' => 'Chat Basic',
            'is_enabled' => true,
            'is_default' => true,
            'supports_vision' => true,
        ]);

        AiModel::create([
            'model_id' => 'chat-effort',
            'label' => 'Chat Effort',
            'is_enabled' => true,
            'supports_effort' => true,
        ]);

        AiModel::create([
            'model_id' => 'img-model',
            'label' => 'Gambar',
            'is_enabled' => true,
            'is_default' => true,
            'is_image' => true,
        ]);
    }

    /** Bind the PIN user the way the ChatPin middleware does. */
    private function chatPage(?int $conversationId = null): Testable
    {
        app()->instance('chatUser', $this->user);

        return Livewire::test(Page::class, $conversationId === null ? [] : ['conversation' => ChatConversation::findOrFail($conversationId)]);
    }

    private function loggedInGet(string $uri)
    {
        $response = $this->post('/chat/pin', ['pin' => '123456']);

        return $this->withUnencryptedCookie('chat_session', $response->getCookie('chat_session', false)->getValue())
            ->get($uri);
    }

    private function sse(string $content): string
    {
        return 'data: '.json_encode(['choices' => [['delta' => ['content' => $content]]]])."\n\n"
            ."data: [DONE]\n\n";
    }

    private function fakeChat(string $content = 'Halo juga'): void
    {
        Http::fake([
            'ai.test/v1/chat/completions' => Http::response($this->sse($content)),
        ]);
    }

    #[Test]
    public function page_is_reachable_and_noindex(): void
    {
        $this->loggedInGet('/chat')
            ->assertOk()
            ->assertSee('noindex', false)
            ->assertSee('data-chat-pending', false)
            ->assertDontSee('sk-TEST');
    }

    #[Test]
    public function send_saves_user_and_assistant_messages(): void
    {
        $this->fakeChat('Halo juga');

        $this->chatPage()
            ->set('prompt', 'Halo')
            ->call('send')
            ->assertHasNoErrors();

        $conversation = ChatConversation::first();

        $this->assertNotNull($conversation);
        $this->assertSame('Halo', $conversation->title);
        $this->assertSame(2, $conversation->messages()->count());
        $this->assertSame('user', $conversation->messages[0]->role);
        $this->assertSame('Halo', $conversation->messages[0]->content);
        $this->assertSame('assistant', $conversation->messages[1]->role);
        $this->assertSame('Halo juga', $conversation->messages[1]->content);
        $this->assertSame('chat-basic', $conversation->messages[1]->model);
    }

    #[Test]
    public function effort_only_for_supporting_model(): void
    {
        $this->fakeChat();

        $this->chatPage()
            ->set('prompt', 'Halo')
            ->set('modelId', 'chat-basic')
            ->set('effort', 'high')
            ->call('send');

        $bodies = [];
        Http::assertSent(function (Request $request) use (&$bodies) {
            $bodies[] = $request->data();

            return true;
        });

        $this->assertArrayNotHasKey('reasoning_effort', $bodies[0]);

        $this->fakeChat();

        $this->chatPage()
            ->set('prompt', 'Halo lagi')
            ->set('modelId', 'chat-effort')
            ->set('effort', 'high')
            ->call('send');

        Http::assertSent(fn (Request $request) => ($request->data()['reasoning_effort'] ?? null) === 'high');
    }

    #[Test]
    public function rejects_invalid_attachment_type_and_size(): void
    {
        $this->chatPage()
            ->set('prompt', 'Lihat ini')
            ->set('uploads', [UploadedFile::fake()->create('jahat.exe', 10)])
            ->call('send')
            ->assertHasErrors('uploads.0');

        $this->chatPage()
            ->set('prompt', 'Lihat ini')
            ->set('uploads', [UploadedFile::fake()->create('besar.pdf', 11 * 1024)])
            ->call('send')
            ->assertHasErrors('uploads.0');

        $this->chatPage()
            ->set('prompt', 'Terlalu banyak')
            ->set('uploads', collect(range(1, 6))
                ->map(fn (int $i) => UploadedFile::fake()->create("file{$i}.pdf", 10))
                ->all())
            ->call('send')
            ->assertHasErrors('uploads');

        $this->assertSame(0, ChatMessage::count());
    }

    #[Test]
    public function image_rejected_for_non_vision_model(): void
    {
        AiModel::where('model_id', 'chat-basic')->update(['supports_vision' => false]);

        $this->chatPage()
            ->set('prompt', 'Lihat gambar ini')
            ->set('modelId', 'chat-basic')
            ->set('uploads', [UploadedFile::fake()->image('foto.png')])
            ->call('send')
            ->assertHasErrors('uploads');

        $this->assertSame(0, ChatMessage::count());
        $this->assertSame(0, count(Storage::disk('local')->allFiles()));
    }

    #[Test]
    public function docx_text_included_in_request(): void
    {
        $this->fakeChat();

        $this->chatPage()
            ->set('prompt', 'Ringkas dokumen ini')
            ->set('uploads', [
                UploadedFile::fake()->createWithContent(
                    'catatan.docx',
                    file_get_contents(__DIR__.'/../fixtures/sample.docx'),
                ),
            ])
            ->call('send')
            ->assertHasNoErrors();

        Http::assertSent(function (Request $request) {
            $messages = $request->data()['messages'] ?? [];
            $last = end($messages);

            return is_string($last['content'] ?? null)
                && str_contains($last['content'], 'Halo dunia')
                && str_contains($last['content'], 'catatan.docx');
        });
    }

    #[Test]
    public function older_images_become_placeholders(): void
    {
        $conversation = $this->user->conversations()->create(['title' => 'Lama']);
        Storage::disk('local')->put('chat/'.$this->user->id.'/lama.png', 'PNGDATA');

        $conversation->messages()->create([
            'role' => 'user',
            'content' => 'Gambar lama',
            'attachments' => [[
                'path' => 'chat/'.$this->user->id.'/lama.png',
                'name' => 'lama.png',
                'mime' => 'image/png',
                'text' => null,
            ]],
        ]);

        $this->fakeChat();

        $this->chatPage()
            ->set('conversationId', $conversation->id)
            ->set('prompt', 'Yang baru')
            ->call('send');

        Http::assertSent(function (Request $request) {
            $messages = $request->data()['messages'] ?? [];
            $first = $messages[0]['content'] ?? '';

            return is_string($first)
                && str_contains($first, '[gambar: lama.png]')
                && ! str_contains($first, 'base64');
        });
    }

    #[Test]
    public function image_mode_exposes_explicit_size_choices(): void
    {
        $this->chatPage()
            ->call('setMode', 'image')
            ->assertSet('imageMode', true)
            ->assertSet('imageSize', 'auto')
            ->assertSee('Ukuran kanvas')
            ->assertSee('Persegi')
            ->assertSee('Potret')
            ->assertSee('Lanskap')
            ->assertSee('Story')
            ->call('setImageSize', '4:5')
            ->assertSet('imageSize', '4:5')
            ->call('setMode', 'image')
            ->assertSet('imageSize', '4:5')
            ->call('setImageSize', 'garbage')
            ->assertSet('imageSize', '4:5');
    }

    #[Test]
    public function starter_prompt_switches_mode_and_fills_composer(): void
    {
        $this->chatPage()
            ->call('useStarter', 'Buat flyer PPDB', 'image')
            ->assertSet('imageMode', true)
            ->assertSet('prompt', 'Buat flyer PPDB')
            ->assertDispatched('chat-focus-composer');
    }

    #[Test]
    public function image_mode_saves_generated_file(): void
    {
        Http::fake([
            'ai.test/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Kucing biru lucu, latar polos, 1:1']]],
            ]),
            'ai.test/v1/images/generations' => Http::response([
                'data' => [['b64_json' => base64_encode('GENERATED-IMAGE')]],
            ]),
        ]);

        $this->chatPage()
            ->set('imageMode', true)
            ->set('modelId', 'img-model')
            ->set('prompt', 'kucing biru')
            ->call('send')
            ->assertHasNoErrors();

        $message = ChatMessage::where('role', 'assistant')->first();

        $this->assertNotNull($message);
        $this->assertNotNull($message->image_path);
        Storage::disk('local')->assertExists($message->image_path);
        $this->assertSame('GENERATED-IMAGE', Storage::disk('local')->get($message->image_path));
    }

    #[Test]
    public function image_mode_plans_the_prompt_and_forwards_the_ratio(): void
    {
        // Handing "buatkan flyer ppdb 16:9" straight to the image model made it
        // return a kitten, so a chat model rewrites it first and the ratio is
        // passed through as aspect_ratio instead of a fixed size.
        $planned = 'Flyer PPDB sekolah, judul besar "PPDB 2027", hijau dan emas, 16:9';

        Http::fake([
            'ai.test/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => $planned]]],
            ]),
            'ai.test/v1/images/generations' => Http::response([
                'data' => [['b64_json' => base64_encode('GENERATED-IMAGE')]],
            ]),
        ]);

        $this->chatPage()
            ->set('imageMode', true)
            ->set('modelId', 'img-model')
            ->set('imageSize', '16:9')
            ->set('prompt', 'buatkan flyer ppdb')
            ->call('send')
            ->assertHasNoErrors();

        $message = ChatMessage::where('role', 'assistant')->first();

        $this->assertSame($planned, $message->content);
        $this->assertSame('16:9', $message->image_requested_ratio);
        $this->assertSame('16:9', $message->image_used_ratio);
        $this->assertFalse($message->image_fell_back);

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/images/generations')
            && $request['prompt'] === $planned
            && $request['aspect_ratio'] === '16:9'
            && ! isset($request['size']));
    }

    #[Test]
    public function image_mode_falls_back_when_the_provider_rejects_the_ratio(): void
    {
        Http::fake([
            'ai.test/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Poster PPDB hijau emas']]],
            ]),
            'ai.test/v1/images/generations' => Http::sequence()
                ->push(['error' => ['code' => 'unsupported_image_controls', 'message' => 'unsupported_image_controls']], 400)
                ->push(['data' => [['b64_json' => base64_encode('SQUARE-IMAGE')]]]),
        ]);

        $this->chatPage()
            ->set('imageMode', true)
            ->set('modelId', 'img-model')
            ->set('imageSize', '16:9')
            ->set('prompt', 'poster ppdb')
            ->call('send')
            ->assertHasNoErrors();

        $message = ChatMessage::where('role', 'assistant')->first();

        $this->assertSame('SQUARE-IMAGE', Storage::disk('local')->get($message->image_path));
        $this->assertSame('16:9', $message->image_requested_ratio);
        $this->assertNull($message->image_used_ratio);
        $this->assertTrue($message->image_fell_back);
        Http::assertSentCount(3); // planner, rejected 16:9 attempt, provider-default retry
    }

    #[Test]
    public function conversation_deleted_while_generating_does_not_crash(): void
    {
        // A generation can take a minute; if the conversation is deleted in the
        // meantime the insert used to break the foreign key and return a 500.
        Http::fake([
            'ai.test/v1/chat/completions' => fn () => Http::response([
                'choices' => [['message' => ['content' => 'Poster PPDB hijau']]],
            ]),
            'ai.test/v1/images/generations' => function () {
                ChatConversation::query()->delete();

                return Http::response(['data' => [['b64_json' => base64_encode('IMG')]]]);
            },
        ]);

        $this->chatPage()
            ->set('imageMode', true)
            ->set('modelId', 'img-model')
            ->set('prompt', 'poster ppdb')
            ->call('send')
            ->assertHasNoErrors();

        $this->assertSame(0, ChatMessage::where('role', 'assistant')->count());
    }

    #[Test]
    public function provider_error_saved_as_error_message(): void
    {
        Http::fake(['ai.test/v1/chat/completions' => Http::response('<html>boom</html>', 500)]);

        $this->chatPage()
            ->set('prompt', 'Halo')
            ->call('send');

        $error = ChatMessage::where('is_error', true)->first();

        $this->assertNotNull($error);
        $this->assertStringContainsString('500', $error->content);
        $this->assertStringNotContainsString('sk-TEST', $error->content);
    }

    #[Test]
    public function empty_answer_shows_placeholder(): void
    {
        Http::fake(['ai.test/v1/chat/completions' => Http::response("data: [DONE]\n\n")]);

        $this->chatPage()
            ->set('prompt', 'Halo')
            ->call('send');

        $assistant = ChatMessage::where('role', 'assistant')->first();

        $this->assertSame('(Tidak ada jawaban)', $assistant->content);
    }

    #[Test]
    public function retry_replaces_error_message(): void
    {
        Http::fakeSequence('ai.test/v1/chat/completions')
            ->push('<html>boom</html>', 500)
            ->push($this->sse('Pulih'), 200);

        $this->chatPage()->set('prompt', 'Halo')->call('send');

        $error = ChatMessage::where('is_error', true)->firstOrFail();

        $this->chatPage()->call('retry', $error->id);

        $this->assertSame(0, ChatMessage::where('is_error', true)->count());
        $this->assertSame('Pulih', ChatMessage::where('role', 'assistant')->first()->content);
    }

    #[Test]
    public function delete_conversation_removes_files(): void
    {
        $conversation = $this->user->conversations()->create(['title' => 'Hapus']);
        Storage::disk('local')->put('chat/'.$this->user->id.'/x.png', 'X');

        $message = $conversation->messages()->create([
            'role' => 'user',
            'content' => 'Hapus aku',
            'attachments' => [[
                'path' => 'chat/'.$this->user->id.'/x.png',
                'name' => 'x.png',
                'mime' => 'image/png',
                'text' => null,
            ]],
        ]);

        $this->chatPage()->call('deleteConversation', $conversation->id);

        $this->assertSame(0, ChatConversation::count());
        $this->assertSame(0, ChatMessage::count());
        Storage::disk('local')->assertMissing('chat/'.$this->user->id.'/x.png');
        $this->assertNotNull($message->id);
    }

    #[Test]
    public function user_cannot_open_other_users_conversation(): void
    {
        $other = ChatUser::create(['name' => 'Budi', 'pin' => Hash::make('654321')]);
        $conversation = $other->conversations()->create(['title' => 'Rahasia']);

        $this->loggedInGet('/chat/'.$conversation->id)->assertNotFound();
    }

    #[Test]
    public function user_cannot_download_other_users_file(): void
    {
        $other = ChatUser::create(['name' => 'Budi', 'pin' => Hash::make('654321')]);
        $conversation = $other->conversations()->create(['title' => 'Rahasia']);
        Storage::disk('local')->put('chat/'.$other->id.'/rahasia.png', 'X');

        $message = $conversation->messages()->create([
            'role' => 'user',
            'content' => 'Rahasia',
            'attachments' => [[
                'path' => 'chat/'.$other->id.'/rahasia.png',
                'name' => 'rahasia.png',
                'mime' => 'image/png',
                'text' => null,
            ]],
        ]);

        $this->loggedInGet('/chat/file/'.$message->id.'/0')->assertNotFound();
    }

    #[Test]
    public function own_file_is_served(): void
    {
        $conversation = $this->user->conversations()->create(['title' => 'Punya sendiri']);
        Storage::disk('local')->put('chat/'.$this->user->id.'/milik.png', 'PNGDATA');

        $message = $conversation->messages()->create([
            'role' => 'user',
            'content' => 'Lihat',
            'attachments' => [[
                'path' => 'chat/'.$this->user->id.'/milik.png',
                'name' => 'milik.png',
                'mime' => 'image/png',
                'text' => null,
            ]],
        ]);

        $response = $this->loggedInGet('/chat/file/'.$message->id.'/0');

        $response->assertOk();
        $this->assertSame('PNGDATA', $response->streamedContent());
    }

    /** All JSON bodies the fake provider received at /chat/completions. */
    private function chatBodies(): array
    {
        return Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/chat/completions'))
            ->map(fn (array $pair) => $pair[0]->data())
            ->values()
            ->all();
    }

    #[Test]
    public function image_mode_uses_the_newest_request_not_the_first(): void
    {
        Http::fake([
            'ai.test/v1/chat/completions' => function (Request $request) {
                return ($request->data()['stream'] ?? false)
                    ? Http::response($this->sse('Halo'))
                    : Http::response(['choices' => [['message' => ['content' => 'prompt terencana']]]]);
            },
            'ai.test/v1/images/generations' => Http::response(['data' => [['b64_json' => base64_encode('PNG')]]]),
        ]);

        $this->chatPage()
            ->set('prompt', 'hello bisa bantu saya')
            ->call('send')
            ->call('setMode', 'image')
            ->set('prompt', 'gambar kucing oranye')
            ->call('send');

        $bodies = $this->chatBodies();
        $planner = end($bodies);

        $this->assertSame('gambar kucing oranye', $planner['messages'][1]['content']);
    }

    #[Test]
    public function streamed_chunks_are_escaped_before_reaching_the_browser(): void
    {
        SiteSetting::set('ai_streaming', '1', 'text', 'ai');
        $this->fakeChat('Awal <img src=x onerror=alert(1)> a<b');

        $streamed = '';
        ob_start(function (string $buffer) use (&$streamed): string {
            $streamed .= $buffer;

            return '';
        });

        try {
            $this->chatPage()->set('prompt', 'Halo')->call('send');
        } finally {
            ob_end_clean();
        }

        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt; a&lt;b', $streamed);
        $this->assertStringNotContainsString('<img src=x', $streamed);
    }

    #[Test]
    public function image_mode_connection_failure_becomes_an_error_message(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));

        $this->chatPage()
            ->call('setMode', 'image')
            ->set('prompt', 'gambar kucing')
            ->call('send');

        $error = ChatMessage::where('is_error', true)->first();

        $this->assertNotNull($error);
        $this->assertStringContainsString('Tidak bisa menghubungi', $error->content);
    }

    #[Test]
    public function planner_connection_failure_falls_back_to_the_users_words(): void
    {
        Http::fake([
            'ai.test/v1/chat/completions' => fn () => throw new ConnectionException('timed out'),
            'ai.test/v1/images/generations' => Http::response(['data' => [['b64_json' => base64_encode('PNG')]]]),
        ]);

        $this->chatPage()
            ->call('setMode', 'image')
            ->set('prompt', 'gambar kucing')
            ->call('send');

        $generated = ChatMessage::whereNotNull('image_path')->first();

        $this->assertNotNull($generated);
        $this->assertSame('gambar kucing', $generated->content);
    }

    #[Test]
    public function documents_can_be_attached_on_a_model_without_vision(): void
    {
        $html = $this->chatPage()->set('modelId', 'chat-effort')->html();

        preg_match('/<input[^>]*type="file"[^>]*>/s', $html, $input);

        $this->assertNotEmpty($input);
        $this->assertStringNotContainsString('disabled', $input[0]);
        $this->assertStringContainsString('accept=".pdf,.docx"', $input[0]);
    }

    #[Test]
    public function vision_models_also_accept_images(): void
    {
        $html = $this->chatPage()->set('modelId', 'chat-basic')->html();

        preg_match('/<input[^>]*type="file"[^>]*>/s', $html, $input);

        $this->assertStringContainsString('accept=".jpg,.jpeg,.png,.webp,.pdf,.docx"', $input[0]);
    }

    #[Test]
    public function a_large_photo_does_not_push_earlier_turns_out_of_the_prompt(): void
    {
        $conversation = $this->user->conversations()->create(['title' => 'Foto']);
        Storage::disk('local')->put('chat/foto.jpg', str_repeat('x', 200 * 1024));

        $conversation->messages()->create(['role' => 'user', 'content' => 'nama saya Budi']);
        $conversation->messages()->create(['role' => 'assistant', 'content' => 'halo Budi']);
        $conversation->messages()->create([
            'role' => 'user',
            'content' => 'lihat foto ini',
            'attachments' => [['path' => 'chat/foto.jpg', 'name' => 'foto.jpg', 'mime' => 'image/jpeg', 'text' => null]],
        ]);
        $error = $conversation->messages()->create(['role' => 'assistant', 'content' => 'gagal', 'is_error' => true]);

        $this->fakeChat();

        $this->chatPage()->set('conversationId', $conversation->id)->call('retry', $error->id);

        $this->assertCount(3, $this->chatBodies()[0]['messages']);
    }

    #[Test]
    public function a_long_document_survives_a_follow_up_question(): void
    {
        $conversation = $this->user->conversations()->create(['title' => 'Buku']);
        $conversation->messages()->create([
            'role' => 'user',
            'content' => 'ringkas',
            'attachments' => [['path' => 'x', 'name' => 'buku.pdf', 'mime' => 'application/pdf', 'text' => str_repeat('a', 120000)]],
        ]);
        $conversation->messages()->create(['role' => 'assistant', 'content' => 'ringkasan']);

        $this->fakeChat();

        $this->chatPage()
            ->set('conversationId', $conversation->id)
            ->set('prompt', 'bab 2 tentang apa?')
            ->call('send');

        $messages = $this->chatBodies()[0]['messages'];

        $this->assertCount(3, $messages);
        $this->assertStringContainsString('buku.pdf', $messages[0]['content']);
    }

    #[Test]
    public function trimmed_history_never_opens_with_an_assistant_turn(): void
    {
        $conversation = $this->user->conversations()->create(['title' => 'Panjang']);
        $conversation->messages()->create(['role' => 'user', 'content' => str_repeat('a', 500000)]);
        $conversation->messages()->create(['role' => 'assistant', 'content' => 'oke']);

        $this->fakeChat();

        $this->chatPage()
            ->set('conversationId', $conversation->id)
            ->set('prompt', 'lanjut')
            ->call('send');

        $messages = $this->chatBodies()[0]['messages'];

        $this->assertCount(1, $messages);
        $this->assertSame('lanjut', $messages[0]['content']);
    }

    #[Test]
    public function stop_route_requires_the_pin(): void
    {
        $this->post('/chat/stop')->assertRedirect(route('chat.pin'));
    }

    #[Test]
    public function stop_route_flags_the_signed_in_user(): void
    {
        $login = $this->post('/chat/pin', ['pin' => '123456']);

        $this->withUnencryptedCookie('chat_session', $login->getCookie('chat_session', false)->getValue())
            ->post('/chat/stop')
            ->assertNoContent();

        $this->assertTrue(Cache::has($this->user->stopCacheKey()));
    }

    #[Test]
    public function stop_keeps_the_partial_answer_in_the_same_conversation(): void
    {
        $key = $this->user->stopCacheKey();

        Http::fake([
            'ai.test/v1/chat/completions' => function () use ($key) {
                Cache::put($key, true, 60);

                return Http::response(
                    'data: '.json_encode(['choices' => [['delta' => ['content' => 'Sebagian']]]])."\n\n"
                    .'data: '.json_encode(['choices' => [['delta' => ['content' => ' lagi']]]])."\n\n"
                );
            },
        ]);

        $page = $this->chatPage()->set('prompt', 'Halo')->call('send');

        $answer = ChatMessage::where('role', 'assistant')->firstOrFail();

        $this->assertSame('Sebagian', $answer->content);
        $this->assertTrue($answer->is_truncated);
        $page->assertSet('conversationId', $answer->chat_conversation_id);
        $this->assertFalse(Cache::has($key));
    }

    #[Test]
    public function a_leftover_stop_flag_does_not_cut_the_next_answer(): void
    {
        Cache::put($this->user->stopCacheKey(), true, 60);
        $this->fakeChat('Utuh');

        $this->chatPage()->set('prompt', 'Halo')->call('send');

        $answer = ChatMessage::where('role', 'assistant')->firstOrFail();

        $this->assertSame('Utuh', $answer->content);
        $this->assertFalse($answer->is_truncated);
    }

    #[Test]
    public function image_mode_has_no_stop_button_because_it_cannot_be_interrupted(): void
    {
        $this->assertStringContainsString('data-chat-stop', $this->chatPage()->html());
        $this->assertStringNotContainsString('data-chat-stop', $this->chatPage()->call('setMode', 'image')->html());
    }

    #[Test]
    public function attachment_type_is_read_from_the_stored_file(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');

        $this->fakeChat();

        // Named .jpg but holding PNG bytes: only reading the stored file can say image/png.
        $this->chatPage()
            ->set('prompt', 'Ini apa?')
            ->set('uploads', [UploadedFile::fake()->createWithContent('foto.jpg', $png)])
            ->call('send')
            ->assertHasNoErrors();

        $message = ChatMessage::where('role', 'user')->firstOrFail();

        $this->assertSame('image/png', $message->attachments[0]['mime']);
    }

    #[Test]
    public function thinking_indicator_keeps_its_flex_layout_while_loading(): void
    {
        $html = $this->chatPage()->html();

        // Without .flex, Livewire forces display:inline-block and the indicator
        // falls out of the centred message column.
        $this->assertStringContainsString('wire:loading.flex.delay.short', $html);
        $this->assertStringContainsString('Sedang berpikir…', $html);
    }

    #[Test]
    public function image_mode_says_it_is_making_an_image(): void
    {
        $html = $this->chatPage()->call('setMode', 'image')->html();

        $this->assertStringContainsString('Sedang membuat gambar…', $html);
        $this->assertStringNotContainsString('Sedang berpikir…', $html);
    }

    #[Test]
    public function sidebar_can_be_collapsed_and_reopened(): void
    {
        $html = $this->chatPage()->html();

        $this->assertStringContainsString('aria-label="Sembunyikan sidebar"', $html);
        $this->assertStringContainsString('aria-label="Tampilkan sidebar"', $html);
    }

    #[Test]
    public function html_classes_are_restored_when_livewire_swaps_the_page(): void
    {
        // wire:navigate resets the classes on <html>, dark mode included.
        $this->loggedInGet('/chat')
            ->assertOk()
            ->assertSee("addEventListener('livewire:navigating'", false)
            ->assertSee('onSwap', false);
    }

    /** Answers the planner per system prompt, and the first generation with FIRST-IMAGE. */
    private function fakeImageEditing(string $plan, array $edits = []): void
    {
        Http::fake([
            'ai.test/v1/chat/completions' => function (Request $request) use ($plan) {
                $isEdit = str_contains((string) ($request->data()['messages'][0]['content'] ?? ''), 'previous image');

                return Http::response(['choices' => [['message' => ['content' => $isEdit ? $plan : 'Flyer PPDB hijau, judul "PPDB 2027"']]]]);
            },
            'ai.test/v1/images/generations' => Http::sequence()
                ->push(['data' => [['b64_json' => base64_encode('FIRST-IMAGE')]]])
                ->push(['data' => [['b64_json' => base64_encode('REDRAWN-IMAGE')]]]),
            'ai.test/v1/images/edits' => $edits === []
                ? Http::response(['data' => [['b64_json' => base64_encode('EDITED-IMAGE')]]])
                : Http::sequence($edits),
        ]);
    }

    private function generateThenAsk(string $change, string $size = 'auto'): void
    {
        $this->chatPage()
            ->set('imageMode', true)
            ->set('modelId', 'img-model')
            ->set('imageSize', '4:5')
            ->set('prompt', 'buatkan flyer ppdb')
            ->call('send')
            ->set('imageSize', $size)
            ->set('prompt', $change)
            ->call('send')
            ->assertHasNoErrors();
    }

    private function lastImageMessage(): ChatMessage
    {
        return ChatMessage::where('role', 'assistant')->whereNotNull('image_path')->reorder('id', 'desc')->firstOrFail();
    }

    #[Test]
    public function asking_for_a_change_sends_the_previous_image_to_the_provider(): void
    {
        // Each request used to be a new picture: "jadikan biru" reached the
        // generator with no image and no context, so it came back unrelated.
        $plan = json_encode(['action' => 'edit', 'prompt' => 'Ganti latar menjadi biru, sisanya tetap sama.']);
        $this->fakeImageEditing($plan);

        $this->generateThenAsk('jadikan latarnya biru');

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/images/edits')
            && $request->isMultipart()
            && $request->hasFile('image', 'FIRST-IMAGE')
            && collect($request->data())->firstWhere('name', 'prompt')['contents'] === 'Ganti latar menjadi biru, sisanya tetap sama.');

        $message = $this->lastImageMessage();

        $this->assertSame('edited', $message->image_edit);
        $this->assertSame('EDITED-IMAGE', Storage::disk('local')->get($message->image_path));
    }

    #[Test]
    public function the_planner_is_told_what_the_previous_image_shows(): void
    {
        $this->fakeImageEditing(json_encode(['action' => 'edit', 'prompt' => 'Ubah.']));

        $this->generateThenAsk('jadikan latarnya biru');

        $bodies = $this->chatBodies();
        $planner = end($bodies);

        $this->assertStringContainsString('Flyer PPDB hijau, judul "PPDB 2027"', $planner['messages'][1]['content']);
        $this->assertStringContainsString('jadikan latarnya biru', $planner['messages'][1]['content']);
    }

    #[Test]
    public function an_edit_keeps_the_canvas_unless_another_size_is_chosen(): void
    {
        $this->fakeImageEditing(json_encode(['action' => 'edit', 'prompt' => 'Ubah.']));

        $this->generateThenAsk('jadikan latarnya biru');

        $this->assertSame('4:5', $this->lastImageMessage()->image_requested_ratio);

        $this->chatPage(ChatMessage::where('role', 'user')->value('chat_conversation_id'))
            ->set('imageMode', true)
            ->set('modelId', 'img-model')
            ->set('imageSize', '16:9')
            ->set('prompt', 'ubah lagi')
            ->call('send');

        $this->assertSame('16:9', $this->lastImageMessage()->image_requested_ratio);
    }

    #[Test]
    public function a_different_subject_in_the_same_chat_is_a_new_image(): void
    {
        $this->fakeImageEditing(json_encode(['action' => 'new', 'prompt' => 'Poster lomba sains']));

        $this->generateThenAsk('sekarang buat poster lomba sains');

        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/images/edits'));

        $message = $this->lastImageMessage();

        $this->assertNull($message->image_edit);
        $this->assertSame('Poster lomba sains', $message->content);
    }

    #[Test]
    public function a_provider_without_an_edit_endpoint_redraws_and_says_so(): void
    {
        $this->fakeImageEditing(
            json_encode(['action' => 'edit', 'prompt' => 'Flyer PPDB, latar biru']),
            [Http::response(['error' => ['message' => 'not found']], 404)],
        );

        $this->generateThenAsk('jadikan latarnya biru');

        $message = $this->lastImageMessage();

        $this->assertSame('redrawn', $message->image_edit);
        $this->assertSame('REDRAWN-IMAGE', Storage::disk('local')->get($message->image_path));
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/images/generations')
            && $request['prompt'] === 'Flyer PPDB, latar biru');

        $this->chatPage($message->chat_conversation_id)->assertSee('bisa berbeda');
    }

    #[Test]
    public function a_provider_wanting_json_edits_gets_the_image_as_a_data_url(): void
    {
        $this->fakeImageEditing(json_encode(['action' => 'edit', 'prompt' => 'Ubah.']), [
            Http::response(['error' => ['message' => 'expected json']], 415),
            Http::response(['data' => [['b64_json' => base64_encode('EDITED-IMAGE')]]]),
        ]);

        $this->generateThenAsk('jadikan latarnya biru');

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/images/edits')
            && ! $request->isMultipart()
            && $request['image']['url'] === 'data:image/png;base64,'.base64_encode('FIRST-IMAGE'));

        $this->assertSame('edited', $this->lastImageMessage()->image_edit);
    }

    #[Test]
    public function a_wrong_api_key_is_reported_instead_of_silently_redrawing(): void
    {
        $this->fakeImageEditing(json_encode(['action' => 'edit', 'prompt' => 'Ubah.']), [Http::response(['error' => ['message' => 'bad key']], 401)]);

        $this->generateThenAsk('jadikan latarnya biru');

        Http::assertSentCount(4);
        $this->assertTrue(ChatMessage::where('role', 'assistant')->reorder('id', 'desc')->first()->is_error);
    }

    #[Test]
    public function an_unreadable_plan_still_edits_with_the_users_own_words(): void
    {
        $this->fakeImageEditing('Maaf, ini bukan JSON.');

        $this->generateThenAsk('jadikan latarnya biru');

        $message = $this->lastImageMessage();

        $this->assertSame('edited', $message->image_edit);
        $this->assertStringContainsString('Flyer PPDB hijau', $message->content);
        $this->assertStringContainsString('jadikan latarnya biru', $message->content);
    }

    #[Test]
    public function a_previous_image_missing_from_disk_means_a_fresh_image(): void
    {
        $this->fakeImageEditing(json_encode(['action' => 'edit', 'prompt' => 'Ubah.']));

        $page = $this->chatPage()
            ->set('imageMode', true)
            ->set('modelId', 'img-model')
            ->set('prompt', 'buatkan flyer ppdb')
            ->call('send');

        Storage::disk('local')->delete($this->lastImageMessage()->image_path);

        $page->set('prompt', 'jadikan latarnya biru')->call('send')->assertHasNoErrors();

        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/images/edits'));
        $this->assertNull($this->lastImageMessage()->image_edit);
    }

    #[Test]
    public function chat_image_mode_sends_the_previous_image_as_a_message_part(): void
    {
        SiteSetting::set('ai_image_mode', 'chat', 'text', 'ai');

        $image = ['choices' => [['message' => ['content' => '', 'images' => [['image_url' => ['url' => 'data:image/png;base64,'.base64_encode('PICTURE')]]]]]]];

        Http::fake(['ai.test/v1/chat/completions' => function (Request $request) use ($image) {
            $first = json_encode($request->data()['messages'][0]['content'] ?? '');

            return match (true) {
                isset($request->data()['modalities']) => Http::response($image),
                str_contains($first, 'previous image') => Http::response(['choices' => [['message' => ['content' => json_encode(['action' => 'edit', 'prompt' => 'Ubah.'])]]]]),
                default => Http::response(['choices' => [['message' => ['content' => 'Flyer']]]]),
            };
        }]);

        $this->generateThenAsk('jadikan latarnya biru');

        $bodies = array_values(array_filter($this->chatBodies(), fn (array $body): bool => isset($body['modalities'])));
        $parts = end($bodies)['messages'][0]['content'];

        $this->assertSame('Ubah.', $parts[0]['text']);
        $this->assertStringStartsWith('data:image/', $parts[1]['image_url']['url']);
        $this->assertSame('edited', $this->lastImageMessage()->image_edit);
    }
}
