<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\ChatPin;
use App\Models\ChatUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatPinTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $pin = '123456'): ChatUser
    {
        return ChatUser::create(['name' => 'Ayu', 'pin' => Hash::make($pin)]);
    }

    #[Test]
    public function guest_is_redirected_to_pin(): void
    {
        $this->get('/chat')->assertRedirect(route('chat.pin'));
    }

    #[Test]
    public function correct_pin_sets_cookie_and_redirects(): void
    {
        $this->user();

        $response = $this->post('/chat/pin', ['pin' => '123456']);

        $response->assertRedirect(route('chat'));
        $response->assertCookie('chat_session');

        $this->withUnencryptedCookie('chat_session', $response->getCookie('chat_session', false)->getValue())
            ->get('/chat')
            ->assertOk();
    }

    #[Test]
    public function wrong_pin_rejected_and_throttled_after_five(): void
    {
        $this->user();

        $this->post('/chat/pin', ['pin' => '000000'])
            ->assertSessionHasErrors('pin');

        for ($i = 0; $i < 4; $i++) {
            $this->post('/chat/pin', ['pin' => '000000']);
        }

        $throttled = $this->post('/chat/pin', ['pin' => '000000']);
        $throttled->assertSessionHasErrors('pin');
        $this->assertStringContainsString('Terlalu banyak', session('errors')->first('pin'));

        // Even the correct PIN is refused while the limiter is active.
        $this->post('/chat/pin', ['pin' => '123456'])
            ->assertRedirect(route('chat.pin'));
    }

    #[Test]
    public function changed_pin_invalidates_cookie(): void
    {
        $user = $this->user();

        $response = $this->post('/chat/pin', ['pin' => '123456']);
        $cookie = $response->getCookie('chat_session', false)->getValue();

        $user->update(['pin' => Hash::make('654321')]);

        $this->withUnencryptedCookie('chat_session', $cookie)
            ->get('/chat')
            ->assertRedirect(route('chat.pin'));
    }

    #[Test]
    public function pin_page_is_noindex(): void
    {
        $this->get('/chat/pin')
            ->assertOk()
            ->assertSee('noindex', false)
            ->assertDontSee('SECRET');
    }

    #[Test]
    public function chat_pin_is_persistent_middleware(): void
    {
        // Livewire updates POST to /livewire/update; without persistence the
        // PIN gate would not run there and app('chatUser') would be unbound.
        $middleware = app(PersistentMiddleware::class)
            ->getPersistentMiddleware();

        $this->assertContains(ChatPin::class, $middleware);
    }

    #[Test]
    public function logout_forgets_cookie(): void
    {
        $this->post('/chat/logout')
            ->assertRedirect(route('chat.pin'))
            ->assertCookieExpired('chat_session');
    }
}
