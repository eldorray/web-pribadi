<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Support\Facades\Storage;

/**
 * Attachment and generated-image files live on the private `local` disk, so
 * nothing is reachable without the PIN. Deleting a conversation or a user has
 * to take the bytes with it.
 */
final class ChatFiles
{
    public static function deleteForMessage(ChatMessage $message): void
    {
        $disk = Storage::disk('local');

        if (is_string($message->image_path) && $message->image_path !== '') {
            $disk->delete($message->image_path);
        }

        foreach ((array) $message->attachments as $attachment) {
            if (is_array($attachment) && isset($attachment['path']) && is_string($attachment['path'])) {
                $disk->delete($attachment['path']);
            }
        }
    }

    public static function deleteForConversation(ChatConversation $conversation): void
    {
        foreach ($conversation->messages()->get() as $message) {
            self::deleteForMessage($message);
        }
    }
}
