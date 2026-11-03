<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Admin\Settings\General;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatUser;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatModelsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function ai_settings_are_not_passed_to_public_views(): void
    {
        SiteSetting::set('site_name', 'Situs Uji', 'text', 'general');
        SiteSetting::set('ai_api_key', 'SECRET', 'text', 'ai');

        $response = $this->get('/');
        $response->assertOk();
        $response->assertDontSee('SECRET');

        $settings = $response->viewData('settings');
        $this->assertArrayNotHasKey('ai_api_key', $settings);
        $this->assertArrayHasKey('site_name', $settings);
    }

    #[Test]
    public function general_settings_page_ignores_ai_group(): void
    {
        SiteSetting::set('ai_api_key', 'SECRET', 'text', 'ai');

        $component = Livewire::actingAs(User::factory()->create())
            ->test(General::class);

        $this->assertArrayNotHasKey('ai_api_key', $component->get('settings'));

        $component->call('save');

        $this->assertSame('SECRET', SiteSetting::get('ai_api_key'));
        $this->assertSame('ai', SiteSetting::where('key', 'ai_api_key')->value('group'));
    }

    #[Test]
    public function message_html_strips_raw_html(): void
    {
        // An HTML block (script) is stripped whole by CommonMark's strip mode,
        // so the markdown below it is what survives.
        $html = ChatMessage::make(['content' => "<script>alert(1)</script>\n\n**b**"])->html();

        $this->assertStringContainsString('<strong>b</strong>', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    #[Test]
    public function deleting_user_cascades_conversations(): void
    {
        $user = ChatUser::create(['name' => 'Ayu', 'pin' => Hash::make('123456')]);
        $conversation = $user->conversations()->create(['title' => 'Halo']);
        $conversation->messages()->create(['role' => 'user', 'content' => 'Halo']);

        $user->delete();

        $this->assertSame(0, ChatConversation::count());
        $this->assertSame(0, ChatMessage::count());
    }

    #[Test]
    public function pin_is_hidden_and_versioned(): void
    {
        $user = ChatUser::create(['name' => 'Ayu', 'pin' => Hash::make('123456')]);

        $this->assertArrayNotHasKey('pin', $user->toArray());
        $this->assertSame(16, strlen($user->pinVersion()));

        $before = $user->pinVersion();
        $user->update(['pin' => Hash::make('654321')]);

        $this->assertNotSame($before, $user->fresh()->pinVersion());
    }
}
