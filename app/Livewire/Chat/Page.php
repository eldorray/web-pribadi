<?php

declare(strict_types=1);

namespace App\Livewire\Chat;

use App\Models\AiModel;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatUser;
use App\Services\AiClient;
use App\Services\AiException;
use App\Services\AttachmentExtractor;
use App\Services\ChatFiles;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * The whole /chat page: conversation list, message thread, composer.
 *
 * Every lookup goes through the signed-in ChatUser's own conversations, so a
 * foreign conversation id is a 404 rather than someone else's history.
 */
class Page extends Component
{
    use WithFileUploads;

    /** Rough prompt budget; oldest turns are dropped past this. */
    private const HISTORY_CHARS = 100000;

    public ?int $conversationId = null;

    public string $prompt = '';

    public ?string $modelId = null;

    public ?string $effort = null;

    public bool $imageMode = false;

    /** @var array<int, TemporaryUploadedFile> */
    public array $uploads = [];

    public function mount(?ChatConversation $conversation = null): void
    {
        if ($conversation !== null) {
            abort_unless($conversation->chat_user_id === $this->chatUser()->getKey(), 404);

            $this->conversationId = $conversation->getKey();
        }

        $default = AiModel::defaultChat();
        $this->modelId = $default?->model_id;
        $this->effort = $default?->supports_effort ? 'medium' : null;
    }

    // ── Composer ────────────────────────────────────────────────────────────

    public function send(): void
    {
        $this->validate([
            'prompt' => ['nullable', 'string', 'max:20000'],
            'uploads' => ['array', 'max:5'],
            'uploads.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf,docx', 'max:10240'],
        ], [
            'prompt.max' => 'Pesan terlalu panjang.',
            'uploads.max' => 'Maksimal 5 lampiran per pesan.',
            'uploads.*.mimes' => 'Lampiran harus gambar, PDF, atau DOCX.',
            'uploads.*.max' => 'Ukuran lampiran maksimal 10 MB.',
        ]);

        $prompt = trim($this->prompt);

        if ($prompt === '' && $this->uploads === []) {
            $this->addError('prompt', 'Tulis pesan atau lampirkan berkas.');

            return;
        }

        $model = $this->imageMode ? $this->resolveImageModel() : $this->resolveChatModel();

        if ($model === null) {
            $this->addError('prompt', $this->imageMode
                ? 'Belum ada model gambar aktif. Aktifkan di admin → Chat AI.'
                : 'Belum ada model chat aktif. Aktifkan di admin → Chat AI.');

            return;
        }

        $attachments = $this->storeUploads();

        if (! $this->imageMode && ! $model->supports_vision && $this->hasImages($attachments)) {
            ChatFiles::deleteForMessage(ChatMessage::make(['attachments' => $attachments]));
            $this->addError('uploads', 'Model ini tidak bisa membaca gambar. Pilih model lain.');

            return;
        }

        $conversation = $this->conversation();
        $isNew = $conversation->wasRecentlyCreated;

        $conversation->messages()->create([
            'role' => 'user',
            'content' => $prompt,
            'attachments' => $attachments ?: null,
        ]);

        if ($isNew) {
            $conversation->update([
                'title' => Str::limit($prompt !== '' ? $prompt : ($attachments[0]['name'] ?? 'Percakapan baru'), 50, ''),
            ]);
        }

        $conversation->touch();

        $this->prompt = '';
        $this->uploads = [];
        $this->resetErrorBag();

        if ($isNew) {
            $this->dispatch('chat-conversation', id: $conversation->getKey());
        }

        $this->respond($conversation, $model);
    }

    public function retry(int $messageId): void
    {
        $message = $this->ownedMessage($messageId);
        abort_unless($message->is_error, 404);

        $conversation = $message->conversation;
        $message->delete();

        $model = $this->imageMode ? $this->resolveImageModel() : $this->resolveChatModel();

        if ($model === null) {
            return;
        }

        $this->respond($conversation, $model);
    }

    public function removeUpload(int $index): void
    {
        unset($this->uploads[$index]);
        $this->uploads = array_values($this->uploads);
    }

    public function toggleImageMode(): void
    {
        $this->imageMode = ! $this->imageMode;

        if ($this->imageMode) {
            $this->modelId = AiModel::defaultImage()?->model_id;
            $this->effort = null;

            return;
        }

        $model = AiModel::defaultChat();
        $this->modelId = $model?->model_id;
        $this->effort = $model?->supports_effort ? 'medium' : null;
    }

    public function updatedModelId(): void
    {
        $model = $this->selectedModel();
        $this->effort = $model?->supports_effort ? ($this->effort ?? 'medium') : null;
    }

    // ── Conversations ───────────────────────────────────────────────────────

    public function newChat(): void
    {
        $this->conversationId = null;
        $this->prompt = '';
        $this->uploads = [];
        $this->resetErrorBag();
        $this->redirect(route('chat'), navigate: true);
    }

    public function openConversation(int $id): void
    {
        $conversation = $this->ownedConversation($id);

        $this->redirect(route('chat', ['conversation' => $conversation->getKey()]), navigate: true);
    }

    public function rename(int $id, string $title): void
    {
        $title = trim($title);

        if ($title === '') {
            return;
        }

        $this->ownedConversation($id)->update(['title' => Str::limit($title, 50, '')]);
    }

    public function deleteConversation(int $id): void
    {
        $conversation = $this->ownedConversation($id);

        ChatFiles::deleteForConversation($conversation);
        $conversation->delete();

        if ($this->conversationId === $id) {
            $this->conversationId = null;
            $this->redirect(route('chat'), navigate: true);
        }
    }

    // ── Rendering ───────────────────────────────────────────────────────────

    public function render()
    {
        $chatUser = $this->chatUser();
        $conversation = $this->conversationId !== null
            ? $chatUser->conversations()->with('messages')->find($this->conversationId)
            : null;

        $selected = $this->selectedModel();

        return view('livewire.chat.page', [
            'chatUser' => $chatUser,
            'conversation' => $conversation,
            'conversations' => $chatUser->conversations()->orderByDesc('updated_at')->take(50)->get(),
            'models' => $this->imageMode
                ? AiModel::image()->orderByDesc('is_default')->orderBy('label')->get()
                : AiModel::chat()->orderByDesc('is_default')->orderBy('label')->get(),
            'supportsEffort' => (bool) $selected?->supports_effort,
            'supportsVision' => (bool) $selected?->supports_vision,
            'efforts' => ['low' => 'Ringkas', 'medium' => 'Seimbang', 'high' => 'Mendalam'],
        ])->layout('layouts.chat', ['title' => $conversation?->title ?? 'Chat']);
    }

    // ── Internals ───────────────────────────────────────────────────────────

    /**
     * Call the provider for the newest state of a conversation and store the
     * answer (or the readable failure) as the next assistant message.
     */
    private function respond(ChatConversation $conversation, AiModel $model): void
    {
        // cPanel has no queue worker and generation can be slow.
        @set_time_limit(180);
        ignore_user_abort(true);

        $client = new AiClient;
        $chatUser = $this->chatUser();

        if ($this->imageMode) {
            $prompt = (string) $conversation->messages()->where('role', 'user')->latest('id')->value('content');

            try {
                $bytes = $client->generateImage($model->model_id, $prompt);
            } catch (AiException $e) {
                $this->storeError($conversation, $e->getMessage());

                return;
            }

            $path = "chat/{$chatUser->getKey()}/gen-".Str::uuid().'.png';
            Storage::disk('local')->put($path, $bytes);

            $conversation->messages()->create([
                'role' => 'assistant',
                'model' => $model->model_id,
                'image_path' => $path,
            ]);
            $conversation->touch();

            return;
        }

        $streaming = AiClient::streamingEnabled();

        $onDelta = function (string $chunk) use ($streaming): void {
            if ($streaming) {
                $this->stream($chunk, el: '#chat-stream');
            }
        };

        $shouldStop = fn (): bool => connection_aborted() === 1;

        try {
            $result = $client->chat(
                $this->buildHistory($conversation),
                $model->model_id,
                $model->supports_effort ? $this->effort : null,
                $onDelta,
                $shouldStop,
            );
        } catch (AiException $e) {
            $this->storeError($conversation, $e->getMessage());

            return;
        }

        $content = trim($result['content']);

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $content === '' ? '(Tidak ada jawaban)' : $result['content'],
            'model' => $model->model_id,
            'effort' => $model->supports_effort ? $this->effort : null,
            'is_truncated' => $result['truncated'],
        ]);

        $conversation->touch();
    }

    private function storeError(ChatConversation $conversation, string $message): void
    {
        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $message,
            'is_error' => true,
        ]);
    }

    /**
     * OpenAI-shaped message list for the provider.
     *
     * Images are only inlined for the newest user message; older ones degrade
     * to a text placeholder so the payload stays small.
     *
     * @return list<array<string, mixed>>
     */
    private function buildHistory(ChatConversation $conversation): array
    {
        $messages = $conversation->messages()->get()->filter(
            fn (ChatMessage $m) => ! $m->is_error && ! ($m->role === 'assistant' && $m->image_path)
        );

        $lastUserId = $messages->filter(fn (ChatMessage $m) => $m->role === 'user')->last()?->getKey();

        $out = [];

        foreach ($messages as $message) {
            if ($message->role !== 'user') {
                $out[] = ['role' => 'assistant', 'content' => (string) $message->content];

                continue;
            }

            $text = (string) $message->content;
            $images = [];

            foreach ((array) $message->attachments as $attachment) {
                if (! is_array($attachment)) {
                    continue;
                }

                $mime = (string) ($attachment['mime'] ?? '');
                $name = (string) ($attachment['name'] ?? 'berkas');

                if (str_starts_with($mime, 'image/')) {
                    if ($message->getKey() === $lastUserId) {
                        $part = $this->imagePart($attachment);

                        if ($part !== null) {
                            $images[] = $part;
                        }
                    } else {
                        $text .= "\n[gambar: {$name}]";
                    }

                    continue;
                }

                if (isset($attachment['text']) && is_string($attachment['text']) && $attachment['text'] !== '') {
                    $text .= "\n\n--- {$name} ---\n".$attachment['text'];
                } else {
                    $text .= "\n\n--- {$name} ---\n(Teks tidak bisa dibaca)";
                }
            }

            if ($images !== []) {
                $parts = $text !== '' ? [['type' => 'text', 'text' => $text]] : [];

                $out[] = ['role' => 'user', 'content' => array_merge($parts, $images)];
            } else {
                $out[] = ['role' => 'user', 'content' => $text];
            }
        }

        // Drop the oldest turns until the prompt fits the character budget.
        while (count($out) > 1 && $this->historySize($out) > self::HISTORY_CHARS) {
            array_shift($out);
        }

        return array_values($out);
    }

    /**
     * @param  array<string, mixed>  $attachment
     * @return array<string, mixed>|null
     */
    private function imagePart(array $attachment): ?array
    {
        $path = (string) ($attachment['path'] ?? '');
        $disk = Storage::disk('local');

        if ($path === '' || ! $disk->exists($path)) {
            return null;
        }

        $bytes = $disk->get($path);

        if ($bytes === null || $bytes === '') {
            return null;
        }

        $mime = (string) ($attachment['mime'] ?? 'image/png');

        return [
            'type' => 'image_url',
            'image_url' => ['url' => 'data:'.$mime.';base64,'.base64_encode($bytes)],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     */
    private function historySize(array $messages): int
    {
        $size = 0;

        foreach ($messages as $message) {
            $content = $message['content'] ?? '';

            if (is_string($content)) {
                $size += strlen($content);

                continue;
            }

            foreach ((array) $content as $part) {
                $size += isset($part['text']) ? strlen((string) $part['text']) : 0;
                $size += isset($part['image_url']['url']) ? strlen((string) $part['image_url']['url']) : 0;
            }
        }

        return $size;
    }

    /**
     * @return list<array{path: string, name: string, mime: string, text: ?string}>
     */
    private function storeUploads(): array
    {
        if ($this->uploads === []) {
            return [];
        }

        $chatUser = $this->chatUser();
        $disk = Storage::disk('local');
        $stored = [];

        foreach ($this->uploads as $upload) {
            $path = $upload->store("chat/{$chatUser->getKey()}", 'local');
            $mime = (string) ($upload->getMimeType() ?? 'application/octet-stream');

            $stored[] = [
                'path' => $path,
                'name' => $upload->getClientOriginalName(),
                'mime' => $mime,
                'text' => AttachmentExtractor::extract($disk->path($path), $mime),
            ];
        }

        return $stored;
    }

    /**
     * @param  list<array{path: string, name: string, mime: string, text: ?string}>  $attachments
     */
    private function hasImages(array $attachments): bool
    {
        foreach ($attachments as $attachment) {
            if (str_starts_with($attachment['mime'], 'image/')) {
                return true;
            }
        }

        return false;
    }

    private function conversation(): ChatConversation
    {
        if ($this->conversationId !== null) {
            return $this->ownedConversation($this->conversationId);
        }

        $conversation = $this->chatUser()->conversations()->create([
            'title' => 'Percakapan baru',
        ]);

        $this->conversationId = $conversation->getKey();

        return $conversation;
    }

    private function ownedConversation(int $id): ChatConversation
    {
        return $this->chatUser()->conversations()->findOrFail($id);
    }

    private function ownedMessage(int $id): ChatMessage
    {
        $conversationIds = $this->chatUser()->conversations()->pluck('id');

        return ChatMessage::whereIn('chat_conversation_id', $conversationIds)->findOrFail($id);
    }

    private function resolveChatModel(): ?AiModel
    {
        $model = AiModel::chat()->where('model_id', (string) $this->modelId)->first();

        return $model ?? AiModel::defaultChat();
    }

    private function resolveImageModel(): ?AiModel
    {
        $model = AiModel::image()->where('model_id', (string) $this->modelId)->first();

        return $model ?? AiModel::defaultImage();
    }

    private function selectedModel(): ?AiModel
    {
        return $this->imageMode ? $this->resolveImageModel() : $this->resolveChatModel();
    }

    private function chatUser(): ChatUser
    {
        return app('chatUser');
    }
}
