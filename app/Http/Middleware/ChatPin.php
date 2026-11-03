<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ChatUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates every /chat route behind the PIN cookie.
 *
 * The cookie holds "{chat_user_id}|{pinVersion}". pinVersion() is a short hash
 * of the stored PIN hash, so changing a PIN in admin immediately invalidates
 * cookies issued before the change without needing a session table.
 */
class ChatPin
{
    public const COOKIE = 'chat_session';

    /** 30 days, in minutes. */
    public const LIFETIME = 43200;

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = self::resolveUser($request);

        if (! $user) {
            Cookie::queue(Cookie::forget(self::COOKIE));

            // Livewire update requests expect JSON; a redirect would be
            // swallowed by fetch and surface as an opaque parse error.
            if ($request->expectsJson() || $request->hasHeader('X-Livewire')) {
                abort(403, 'Sesi chat sudah berakhir. Muat ulang halaman.');
            }

            return redirect()->route('chat.pin');
        }

        app()->instance('chatUser', $user);

        return $next($request);
    }

    /**
     * Read and validate the PIN cookie. Returns null for a missing, malformed,
     * unknown or stale cookie — all treated the same way.
     */
    public static function resolveUser(Request $request): ?ChatUser
    {
        $raw = $request->cookie(self::COOKIE);

        if (! is_string($raw) || ! str_contains($raw, '|')) {
            return null;
        }

        [$id, $version] = explode('|', $raw, 2);

        if (! ctype_digit($id) || $version === '') {
            return null;
        }

        $user = ChatUser::find((int) $id);

        if (! $user || ! hash_equals($user->pinVersion(), $version)) {
            return null;
        }

        return $user;
    }

    public static function cookieValue(ChatUser $user): string
    {
        return $user->getKey().'|'.$user->pinVersion();
    }
}
