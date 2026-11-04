<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradingRubric extends Model
{
    protected $fillable = ['chat_user_id', 'name', 'guidance', 'criteria'];

    protected function casts(): array
    {
        return ['criteria' => 'array'];
    }

    /** @return BelongsTo<ChatUser, $this> */
    public function chatUser(): BelongsTo
    {
        return $this->belongsTo(ChatUser::class);
    }
}
