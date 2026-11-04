<?php

declare(strict_types=1);

namespace App\Livewire\Admin\ChatAi;

use App\Models\AiModel;
use App\Models\ChatUser;
use App\Models\SiteSetting;
use App\Services\AiClient;
use App\Services\AiException;
use App\Services\ChatFiles;
use App\Services\GradingFiles;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

/**
 * Everything the chat needs to reach a provider: connection, models, people.
 *
 * The API key is write-only from the browser's point of view — it is never put
 * into a public property, so it cannot end up in the Livewire payload or HTML.
 */
class Index extends Component
{
    public string $baseUrl = '';

    public string $apiKey = '';

    public string $imageMode = 'images';

    public bool $streaming = true;

    public string $newUserName = '';

    public string $newUserPin = '';

    public string $connectionMessage = '';

    public bool $connectionFailed = false;

    public string $modelsMessage = '';

    public string $newModelId = '';

    /** @var array<int, string> new PIN per chat user id, keyed by id */
    public array $pinEdits = [];

    public function mount(): void
    {
        $this->baseUrl = AiClient::baseUrl();
        $this->imageMode = AiClient::imageMode();
        $this->streaming = AiClient::streamingEnabled();
        $this->apiKey = '';
    }

    public function saveConnection(): void
    {
        $this->validate([
            'baseUrl' => ['required', 'url', 'max:255'],
            'apiKey' => ['nullable', 'string', 'max:255'],
            'imageMode' => ['required', 'in:images,chat'],
            'streaming' => ['boolean'],
        ], [
            'baseUrl.required' => 'Base URL wajib diisi.',
            'baseUrl.url' => 'Base URL harus berupa URL yang valid.',
            'imageMode.in' => 'Mode gambar tidak dikenal.',
        ]);

        SiteSetting::set('ai_base_url', rtrim($this->baseUrl, '/'), 'text', 'ai');
        SiteSetting::set('ai_image_mode', $this->imageMode, 'text', 'ai');
        SiteSetting::set('ai_streaming', $this->streaming ? '1' : '0', 'text', 'ai');

        // An empty field means "keep the key that is already stored".
        if (trim($this->apiKey) !== '') {
            AiClient::setApiKey(trim($this->apiKey));
            $this->apiKey = '';
        }

        $this->connectionMessage = 'Koneksi tersimpan.';
        $this->connectionFailed = false;
    }

    public function testConnection(): void
    {
        $this->connectionMessage = '';
        $this->connectionFailed = false;

        try {
            $models = (new AiClient)->listModels();
            $this->connectionMessage = 'Terhubung. '.count($models).' model tersedia.';
        } catch (AiException $e) {
            $this->connectionMessage = $e->getMessage();
            $this->connectionFailed = true;
        }
    }

    public function fetchModels(): void
    {
        $this->modelsMessage = '';

        try {
            $models = (new AiClient)->listModels();
        } catch (AiException $e) {
            $this->modelsMessage = $e->getMessage();

            return;
        }

        $existing = AiModel::pluck('model_id')->all();
        $added = 0;

        foreach ($models as $modelId) {
            if (in_array($modelId, $existing, true)) {
                continue;
            }

            AiModel::create([
                'model_id' => $modelId,
                'label' => $modelId,
                'is_enabled' => false,
            ]);
            $added++;
        }

        $this->modelsMessage = $added > 0
            ? "{$added} model baru ditambahkan (nonaktif). Aktifkan yang ingin dipakai."
            : 'Tidak ada model baru.';
    }

    public function addModel(): void
    {
        $this->validate([
            'newModelId' => ['required', 'string', 'max:120'],
        ], [
            'newModelId.required' => 'Nama model wajib diisi.',
        ]);

        $modelId = trim($this->newModelId);

        if (AiModel::where('model_id', $modelId)->exists()) {
            $this->modelsMessage = 'Model itu sudah ada di daftar.';

            return;
        }

        AiModel::create([
            'model_id' => $modelId,
            'label' => $modelId,
            'is_enabled' => true,
        ]);

        $this->newModelId = '';
        $this->modelsMessage = 'Model ditambahkan.';
    }

    public function updateModel(int $id, string $field, bool|string $value): void
    {
        if (! in_array($field, ['label', 'is_enabled', 'is_default', 'supports_effort', 'supports_vision', 'is_image', 'is_transcribe'], true)) {
            return;
        }

        $model = AiModel::findOrFail($id);

        if ($field === 'label') {
            $label = trim((string) $value);
            $model->update(['label' => $label === '' ? $model->model_id : mb_substr($label, 0, 120)]);

            return;
        }

        $model->update([$field => (bool) $value]);

        // Exactly one default per kind keeps the chat page predictable.
        if ($field === 'is_default' && (bool) $value) {
            AiModel::where('id', '!=', $model->id)
                ->where('is_image', $model->is_image)
                ->where('is_transcribe', $model->is_transcribe)
                ->update(['is_default' => false]);
        }
    }

    public function deleteModel(int $id): void
    {
        AiModel::findOrFail($id)->delete();
    }

    public function addUser(): void
    {
        $this->validate([
            'newUserName' => ['required', 'string', 'max:60'],
            'newUserPin' => ['required', 'digits:6'],
        ], [
            'newUserName.required' => 'Nama wajib diisi.',
            'newUserPin.required' => 'PIN wajib diisi.',
            'newUserPin.digits' => 'PIN harus 6 angka.',
        ]);

        ChatUser::create([
            'name' => trim($this->newUserName),
            'pin' => Hash::make($this->newUserPin),
        ]);

        $this->newUserName = '';
        $this->newUserPin = '';
    }

    public function changePin(int $id): void
    {
        $pin = (string) ($this->pinEdits[$id] ?? '');

        $validated = validator(['pin' => $pin], [
            'pin' => ['required', 'digits:6'],
        ], [
            'pin.required' => 'PIN wajib diisi.',
            'pin.digits' => 'PIN harus 6 angka.',
        ])->validate();

        ChatUser::findOrFail($id)->update(['pin' => Hash::make($validated['pin'])]);

        unset($this->pinEdits[$id]);

        // Changing the PIN invalidates the cookies issued with the old one.
        session()->flash('chat-user-message', 'PIN diperbarui. Sesi lama otomatis berakhir.');
    }

    public function deleteUser(int $id): void
    {
        $user = ChatUser::findOrFail($id);

        foreach ($user->conversations()->get() as $conversation) {
            ChatFiles::deleteForConversation($conversation);
        }

        GradingFiles::deleteForUser($user);

        $user->delete();
    }

    public function render()
    {
        return view('livewire.admin.chat-ai.index', [
            'chatModels' => AiModel::where('is_image', false)->orderBy('label')->get(),
            'imageModels' => AiModel::where('is_image', true)->orderBy('label')->get(),
            'chatUsers' => ChatUser::orderBy('name')->get(),
            'hasApiKey' => AiClient::hasApiKey(),
        ])->layout('layouts.admin', ['pageTitle' => 'Chat AI']);
    }
}
