<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A person allowed into /chat. Two rows expected: the owner and their partner.
 *
 * The PIN is a shared secret, not a password: it is stored hashed and compared
 * with Hash::check in a loop over these (few) rows. pinVersion() lets a cookie
 * survive PIN changes only until the PIN actually changes.
 */
class ChatUser extends Model
{
    protected $fillable = ['name', 'pin'];

    protected $hidden = ['pin'];

    /** @return HasMany<ChatConversation, $this> */
    public function conversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class);
    }

    /**
     * Short fingerprint of the current PIN, stored in the session cookie so a
     * PIN changed in admin invalidates cookies handed out before the change.
     */
    public function pinVersion(): string
    {
        return substr(hash('sha256', (string) $this->pin), 0, 16);
    }
}
