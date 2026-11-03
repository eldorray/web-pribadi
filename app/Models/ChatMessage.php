<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ChatMessage extends Model
{
    protected $fillable = [
        'chat_conversation_id',
        'role',
        'content',
        'model',
        'effort',
        'image_path',
        'attachments',
        'is_error',
        'is_truncated',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'is_error' => 'boolean',
            'is_truncated' => 'boolean',
        ];
    }

    /** @return BelongsTo<ChatConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    /**
     * Markdown → HTML for assistant replies. Raw HTML in the model output is
     * stripped and unsafe links are dropped, so a provider cannot inject markup
     * into the page.
     */
    public function html(): string
    {
        return Str::markdown((string) $this->content, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}
