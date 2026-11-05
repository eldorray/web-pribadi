<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Admin\ChatAi\Index;
use App\Models\AiModel;
use App\Models\ChatConversation;
use App\Models\ChatUser;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\AiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminChatAiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guest_cannot_open(): void
    {
        $this->get('/admin/chat-ai')->assertRedirect(route('login'));
    }

    #[Test]
    public function api_key_saved_encrypted_and_not_rendered(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(Index::class)
            ->set('baseUrl', 'https://ai.test/v1')
            ->set('apiKey', 'sk-SECRET')
            ->call('saveConnection')
            ->assertSet('apiKey', '')
            ->assertHasNoErrors();

        $stored = SiteSetting::get('ai_api_key');
        $this->assertNotSame('sk-SECRET', $stored);
        $this->assertTrue(AiClient::hasApiKey());

        $this->actingAs(User::factory()->create())
            ->get('/admin/chat-ai')
            ->assertOk()
            ->assertDontSee('sk-SECRET');
    }

    #[Test]
    public function empty_api_key_keeps_existing(): void
    {
        AiClient::setApiKey('sk-EXISTING');

        Livewire::actingAs(User::factory()->create())
            ->test(Index::class)
            ->set('apiKey', '')
            ->call('saveConnection');

        $this->assertSame('sk-EXISTING', AiClient::apiKey());
    }

    #[Test]
    public function base_url_must_be_url(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(Index::class)
            ->set('baseUrl', 'bukan-url')
            ->call('saveConnection')
            ->assertHasErrors(['baseUrl']);
    }

    #[Test]
    public function fetch_models_upserts(): void
    {
        AiClient::setApiKey('sk-KEY');

        AiModel::create(['model_id' => 'model-lama', 'label' => 'Lama', 'is_enabled' => true]);

        Http::fake([
            '*/models' => Http::response(['data' => [['id' => 'model-lama'], ['id' => 'model-baru']]]),
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test(Index::class)
            ->call('fetchModels');

        $this->assertSame(2, AiModel::count());

        $baru = AiModel::where('model_id', 'model-baru')->first();
        $this->assertNotNull($baru);
        $this->assertFalse($baru->is_enabled);
        $this->assertSame('model-baru', $baru->label);

        // Existing row is not re-enabled or duplicated.
        $this->assertTrue(AiModel::where('model_id', 'model-lama')->first()->is_enabled);
    }

    #[Test]
    public function setting_default_clears_other_chat_models(): void
    {
        $a = AiModel::create(['model_id' => 'a', 'label' => 'A', 'is_default' => true]);
        $b = AiModel::create(['model_id' => 'b', 'label' => 'B']);
        $image = AiModel::create(['model_id' => 'img', 'label' => 'Img', 'is_default' => true, 'is_image' => true]);

        Livewire::actingAs(User::factory()->create())
            ->test(Index::class)
            ->call('updateModel', $b->id, 'is_default', true);

        $this->assertFalse($a->fresh()->is_default);
        $this->assertTrue($b->fresh()->is_default);
        // Image models keep their own default.
        $this->assertTrue($image->fresh()->is_default);
    }

    #[Test]
    public function model_flags_are_labelled_clearly(): void
    {
        // "Gambar" alone was read as "supports images", so people ticked it on
        // chat models and the model vanished from the chat picker.
        $this->actingAs(User::factory()->create())
            ->get('/admin/chat-ai')
            ->assertOk()
            ->assertSee('Baca gambar')
            ->assertSee('Buat gambar');
    }

    #[Test]
    public function each_model_is_listed_once_under_its_kind(): void
    {
        // A transcription model used to stay in the chat table with "Aktif"
        // ticked, while the chat picker silently left it out.
        AiModel::create(['model_id' => 'chat', 'label' => 'Chat']);
        AiModel::create(['model_id' => 'img', 'label' => 'Img', 'is_image' => true]);
        AiModel::create(['model_id' => 'voice', 'label' => 'Voice', 'is_transcribe' => true]);
        AiModel::create(['model_id' => 'both', 'label' => 'Both', 'is_image' => true, 'is_transcribe' => true]);

        $page = Livewire::actingAs(User::factory()->create())->test(Index::class);

        $this->assertSame(['chat'], $page->viewData('chatModels')->pluck('model_id')->all());
        $this->assertSame(['both', 'img'], $page->viewData('imageModels')->pluck('model_id')->all());
        $this->assertSame(['voice'], $page->viewData('transcribeModels')->pluck('model_id')->all());
        $page->assertSee('Model transkripsi');
    }

    #[Test]
    public function a_model_is_only_one_kind_at_a_time(): void
    {
        $voice = AiModel::create(['model_id' => 'voice', 'label' => 'Voice', 'is_transcribe' => true]);
        $image = AiModel::create(['model_id' => 'img', 'label' => 'Img', 'is_image' => true]);

        Livewire::actingAs(User::factory()->create())
            ->test(Index::class)
            ->call('updateModel', $voice->id, 'is_image', true)
            ->call('updateModel', $image->id, 'is_transcribe', true);

        $this->assertTrue($voice->fresh()->is_image);
        $this->assertFalse($voice->fresh()->is_transcribe);
        $this->assertTrue($image->fresh()->is_transcribe);
        $this->assertFalse($image->fresh()->is_image);
    }

    #[Test]
    public function changing_kind_does_not_carry_the_default_along(): void
    {
        $chat = AiModel::create(['model_id' => 'chat', 'label' => 'Chat', 'is_default' => true]);
        $voice = AiModel::create(['model_id' => 'voice', 'label' => 'Voice', 'is_transcribe' => true, 'is_default' => true]);

        Livewire::actingAs(User::factory()->create())
            ->test(Index::class)
            ->call('updateModel', $chat->id, 'is_transcribe', true);

        $this->assertFalse($chat->fresh()->is_default);
        $this->assertTrue($voice->fresh()->is_default);
    }

    #[Test]
    public function image_model_with_a_stray_transcribe_flag_is_not_used_for_audio(): void
    {
        AiModel::create(['model_id' => 'both', 'label' => 'Both', 'is_enabled' => true, 'is_image' => true, 'is_transcribe' => true]);

        $this->assertNull(AiModel::defaultTranscribe());

        AiModel::create(['model_id' => 'voice', 'label' => 'Voice', 'is_enabled' => true, 'is_transcribe' => true]);

        $this->assertSame('voice', AiModel::defaultTranscribe()?->model_id);
    }

    #[Test]
    public function add_user_hashes_pin(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(Index::class)
            ->set('newUserName', 'Ayu')
            ->set('newUserPin', '123456')
            ->call('addUser')
            ->assertHasNoErrors();

        $user = ChatUser::first();
        $this->assertSame('Ayu', $user->name);
        $this->assertNotSame('123456', $user->pin);
        $this->assertTrue(Hash::check('123456', $user->pin));
    }

    #[Test]
    public function pin_must_be_six_digits(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(Index::class)
            ->set('newUserName', 'Ayu')
            ->set('newUserPin', '12ab')
            ->call('addUser')
            ->assertHasErrors(['newUserPin']);

        $this->assertSame(0, ChatUser::count());
    }

    #[Test]
    public function change_pin_updates_hash(): void
    {
        $user = ChatUser::create(['name' => 'Ayu', 'pin' => Hash::make('123456')]);

        Livewire::actingAs(User::factory()->create())
            ->test(Index::class)
            ->set("pinEdits.{$user->id}", '654321')
            ->call('changePin', $user->id);

        $this->assertTrue(Hash::check('654321', $user->fresh()->pin));
    }

    #[Test]
    public function delete_user_removes_conversations(): void
    {
        $user = ChatUser::create(['name' => 'Ayu', 'pin' => Hash::make('123456')]);
        $user->conversations()->create(['title' => 'Halo']);

        Livewire::actingAs(User::factory()->create())
            ->test(Index::class)
            ->call('deleteUser', $user->id);

        $this->assertSame(0, ChatUser::count());
        $this->assertSame(0, ChatConversation::count());
    }
}
