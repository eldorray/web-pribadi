<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use Illuminate\Support\Facades\Storage;

class ChatFileController extends Controller
{
    /**
     * Serve one attachment or generated image out of the private disk.
     *
     * The route sits behind ChatPin, and ownership is re-checked here so a
     * guessed message id cannot read someone else's file.
     */
    public function __invoke(ChatMessage $message, string $index)
    {
        $chatUser = app('chatUser');

        abort_unless(
            $message->conversation !== null && $message->conversation->chat_user_id === $chatUser->getKey(),
            404,
        );

        $disk = Storage::disk('local');
        $download = request()->boolean('download');

        if ($index === 'image') {
            $path = $message->image_path;
            $name = 'gambar-'.$message->getKey().'.png';
        } else {
            $attachment = $message->attachments[$index] ?? null;

            abort_unless(is_array($attachment), 404);

            $path = $attachment['path'] ?? null;
            $name = (string) ($attachment['name'] ?? 'berkas');
        }

        abort_unless(is_string($path) && $path !== '' && $disk->exists($path), 404);

        $headers = ['Cache-Control' => 'private, max-age=3600', 'X-Content-Type-Options' => 'nosniff'];

        return $download
            ? $disk->download($path, $name, $headers)
            : $disk->response($path, $name, $headers);
    }
}
