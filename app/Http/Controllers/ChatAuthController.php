<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\ChatPin;
use App\Models\ChatUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class ChatAuthController extends Controller
{
    /** Wrong PINs allowed per IP per minute. */
    private const MAX_ATTEMPTS = 5;

    public function showPin(Request $request)
    {
        if (ChatPin::resolveUser($request)) {
            return redirect()->route('chat');
        }

        return view('chat.pin');
    }

    public function submitPin(Request $request)
    {
        $request->validate([
            'pin' => ['required', 'digits:6'],
        ], [
            'pin.required' => 'PIN wajib diisi.',
            'pin.digits' => 'PIN harus 6 angka.',
        ]);

        $key = 'chat-pin:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);

            return redirect()->route('chat.pin')
                ->withErrors(['pin' => "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik."]);
        }

        $pin = (string) $request->input('pin');
        $match = null;

        // Two rows at most — a loop of Hash::check is cheaper than a lookup key.
        foreach (ChatUser::all() as $user) {
            if (Hash::check($pin, $user->pin)) {
                $match = $user;
                break;
            }
        }

        if (! $match) {
            RateLimiter::hit($key, 60);

            return redirect()->route('chat.pin')
                ->withErrors(['pin' => 'PIN tidak dikenali.']);
        }

        RateLimiter::clear($key);

        Cookie::queue(
            Cookie::make(ChatPin::COOKIE, ChatPin::cookieValue($match), ChatPin::LIFETIME, httpOnly: true, sameSite: 'lax')
        );

        return redirect()->route('chat');
    }

    public function logout()
    {
        Cookie::queue(Cookie::forget(ChatPin::COOKIE));

        return redirect()->route('chat.pin');
    }
}
