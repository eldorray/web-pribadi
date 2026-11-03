<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Thin wrapper around any OpenAI-compatible provider.
 *
 * Everything (base URL, key, model list, image mode) is configured from
 * /admin/chat-ai, so swapping providers never needs a deploy. The key is stored
 * encrypted and is never placed in an exception message, log line, or view.
 */
class AiClient
{
    public const DEFAULT_BASE_URL = 'https://api.openai.com/v1';

    /** Request timeout in seconds; a long answer can legitimately take a while. */
    private const TIMEOUT = 120;

    private const CONNECT_TIMEOUT = 10;

    public static function setApiKey(string $plain): void
    {
        SiteSetting::set('ai_api_key', Crypt::encryptString($plain), 'text', 'ai');
    }

    public static function hasApiKey(): bool
    {
        $plain = self::apiKey();

        return $plain !== null && trim($plain) !== '';
    }

    public static function apiKey(): ?string
    {
        $stored = SiteSetting::get('ai_api_key');

        if (! is_string($stored) || $stored === '') {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (Throwable) {
            // A key encrypted with a previous APP_KEY is unreadable, not fatal.
            return null;
        }
    }

    public static function baseUrl(): string
    {
        $url = trim((string) SiteSetting::get('ai_base_url', self::DEFAULT_BASE_URL));

        return $url === '' ? self::DEFAULT_BASE_URL : rtrim($url, '/');
    }

    public static function imageMode(): string
    {
        return SiteSetting::get('ai_image_mode', 'images') === 'chat' ? 'chat' : 'images';
    }

    public static function streamingEnabled(): bool
    {
        return (string) SiteSetting::get('ai_streaming', '1') !== '0';
    }

    /**
     * Model ids the provider advertises.
     *
     * @return list<string>
     */
    public function listModels(): array
    {
        $response = $this->request()->get('/models');
        $json = $this->decode($response);

        $models = [];

        foreach ((array) ($json['data'] ?? []) as $row) {
            if (is_array($row) && isset($row['id']) && is_string($row['id'])) {
                $models[] = $row['id'];
            }
        }

        if ($models === []) {
            throw new AiException('Penyedia tidak mengembalikan daftar model.');
        }

        sort($models);

        return array_values(array_unique($models));
    }

    /**
     * Send a chat completion.
     *
     * With $onDelta the request streams and each text fragment is handed to the
     * callback as it arrives; without it the whole answer is returned at once.
     *
     * @param  list<array<string, mixed>>  $messages  OpenAI-shaped messages
     * @param  callable(string): void|null  $onDelta
     * @param  callable(): bool|null  $shouldStop
     * @return array{content: string, truncated: bool}
     */
    public function chat(
        array $messages,
        string $model,
        ?string $effort = null,
        ?callable $onDelta = null,
        ?callable $shouldStop = null,
    ): array {
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'stream' => $onDelta !== null,
        ];

        if ($effort !== null && $effort !== '') {
            $payload['reasoning_effort'] = $effort;
        }

        if ($onDelta === null) {
            $json = $this->decode($this->request()->post('/chat/completions', $payload));
            $content = $json['choices'][0]['message']['content'] ?? '';

            if (is_array($content)) {
                $content = $this->flattenParts($content);
            }

            return ['content' => (string) $content, 'truncated' => false];
        }

        return $this->streamChat($payload, $onDelta, $shouldStop);
    }

    /**
     * Generate one image and return the raw bytes.
     *
     * Two provider shapes are supported because OpenAI-compatible gateways
     * disagree: /images/generations, or /chat/completions with image modality.
     */
    public function generateImage(string $model, string $prompt): string
    {
        $bytes = self::imageMode() === 'chat'
            ? $this->imageViaChat($model, $prompt)
            : $this->imageViaImagesEndpoint($model, $prompt);

        if ($bytes === '') {
            throw new AiException('Penyedia tidak mengembalikan gambar.');
        }

        return $bytes;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  callable(string): void  $onDelta
     * @param  callable(): bool|null  $shouldStop
     * @return array{content: string, truncated: bool}
     */
    private function streamChat(array $payload, callable $onDelta, ?callable $shouldStop): array
    {
        try {
            $response = $this->request()
                ->withOptions(['stream' => true])
                ->post('/chat/completions', $payload);
        } catch (ConnectionException $e) {
            throw new AiException('Tidak bisa menghubungi penyedia AI. Periksa base URL.');
        }

        if (! $response->successful()) {
            throw $this->failure($response);
        }

        $body = $response->toPsrResponse()->getBody();
        $buffer = '';
        $content = '';
        $truncated = false;

        while (! $body->eof()) {
            $buffer .= $body->read(1024);

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);

                if ($line === '' || ! str_starts_with($line, 'data:')) {
                    continue;
                }

                $data = trim(substr($line, 5));

                if ($data === '[DONE]') {
                    break 2;
                }

                $json = json_decode($data, true);

                if (! is_array($json)) {
                    continue;
                }

                $delta = $json['choices'][0]['delta']['content'] ?? null;

                if (is_string($delta) && $delta !== '') {
                    $content .= $delta;
                    $onDelta($delta);
                }

                if ($shouldStop !== null && $shouldStop()) {
                    $truncated = true;
                    break 2;
                }
            }
        }

        return ['content' => $content, 'truncated' => $truncated];
    }

    private function imageViaImagesEndpoint(string $model, string $prompt): string
    {
        $json = $this->decode($this->request()->post('/images/generations', [
            'model' => $model,
            'prompt' => $prompt,
            'size' => '1024x1024',
            'n' => 1,
        ]));

        $row = $json['data'][0] ?? null;

        if (! is_array($row)) {
            throw new AiException('Penyedia tidak mengembalikan gambar.');
        }

        if (isset($row['b64_json']) && is_string($row['b64_json'])) {
            return $this->decodeBase64($row['b64_json']);
        }

        if (isset($row['url']) && is_string($row['url'])) {
            return $this->fetchImage($row['url']);
        }

        throw new AiException('Penyedia tidak mengembalikan gambar.');
    }

    private function imageViaChat(string $model, string $prompt): string
    {
        $json = $this->decode($this->request()->post('/chat/completions', [
            'model' => $model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'modalities' => ['image', 'text'],
        ]));

        $images = $json['choices'][0]['message']['images'] ?? null;

        if (! is_array($images) || $images === []) {
            throw new AiException('Penyedia tidak mengembalikan gambar.');
        }

        $first = $images[0];
        $url = $first['image_url']['url'] ?? $first['image_url'] ?? $first['url'] ?? null;

        if (! is_string($url) || $url === '') {
            throw new AiException('Penyedia tidak mengembalikan gambar.');
        }

        return $this->fetchImage($url);
    }

    private function fetchImage(string $url): string
    {
        if (str_starts_with($url, 'data:')) {
            $comma = strpos($url, ',');

            return $comma === false ? '' : $this->decodeBase64(substr($url, $comma + 1));
        }

        $response = Http::timeout(self::TIMEOUT)->get($url);

        if (! $response->successful()) {
            throw new AiException('Gagal mengunduh gambar dari penyedia ('.$response->status().').');
        }

        return $response->body();
    }

    private function decodeBase64(string $value): string
    {
        $decoded = base64_decode(preg_replace('/\s+/', '', $value) ?? '', true);

        return $decoded === false ? '' : $decoded;
    }

    /**
     * @param  array<int, mixed>  $parts
     */
    private function flattenParts(array $parts): string
    {
        $text = '';

        foreach ($parts as $part) {
            if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                $text .= $part['text'];
            }
        }

        return $text;
    }

    private function request(): PendingRequest
    {
        $key = self::apiKey();

        if ($key === null || trim($key) === '') {
            throw new AiException('API key belum diatur di admin.');
        }

        return Http::baseUrl(self::baseUrl())
            ->withToken(trim($key))
            ->acceptJson()
            ->asJson()
            ->timeout(self::TIMEOUT)
            ->connectTimeout(self::CONNECT_TIMEOUT);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        if (! $response->successful()) {
            throw $this->failure($response);
        }

        $json = json_decode($response->body(), true);

        if (! is_array($json)) {
            throw new AiException('Penyedia mengembalikan respons yang tidak dikenali ('.$response->status().'). Periksa base URL.');
        }

        return $json;
    }

    /**
     * Build a readable error that names the status and the provider's own
     * message when it sent one — never the request headers.
     */
    private function failure(Response $response): AiException
    {
        $status = $response->status();
        $json = json_decode($response->body(), true);
        $detail = is_array($json) ? ($json['error']['message'] ?? $json['message'] ?? null) : null;

        $message = 'Penyedia AI menolak permintaan (HTTP '.$status.').';

        if (is_string($detail) && $detail !== '') {
            $message .= ' '.$detail;
        } elseif ($status === 401 || $status === 403) {
            $message .= ' Periksa API key di admin.';
        } elseif ($status === 404) {
            $message .= ' Periksa base URL dan nama model.';
        }

        return new AiException($message);
    }
}
