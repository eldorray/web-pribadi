<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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
     *
     * $aspectRatio is a hint like "16:9". Providers that only do squares reject
     * it with unsupported_image_controls, so the call is retried without it
     * rather than failing the request.
     */
    public function generateImage(string $model, string $prompt, ?string $aspectRatio = null): string
    {
        try {
            $bytes = self::imageMode() === 'chat'
                ? $this->imageViaChat($model, $prompt)
                : $this->imageViaImagesEndpoint($model, $prompt, $aspectRatio);
        } catch (AiException $e) {
            if ($aspectRatio === null || ! str_contains($e->getMessage(), 'unsupported_image_controls')) {
                throw $e;
            }

            $bytes = self::imageMode() === 'chat'
                ? $this->imageViaChat($model, $prompt)
                : $this->imageViaImagesEndpoint($model, $prompt, null);
        }

        if ($bytes === '') {
            throw new AiException('Penyedia tidak mengembalikan gambar.');
        }

        return $bytes;
    }

    /**
     * Turn a conversational request ("buatkan saya flyer ppdb 16:9") into a
     * prompt an image model can follow. Sent as-is, that sentence is mostly
     * instruction to us, not a description of the picture — which is how a
     * request for a school flyer comes back as a kitten.
     *
     * Returns null when no chat model is available or the rewrite failed; the
     * caller then falls back to the user's own words.
     */
    public function planImagePrompt(string $request, string $model): ?string
    {
        $request = trim($request);

        if ($request === '') {
            return null;
        }

        try {
            $result = $this->chat([
                ['role' => 'system', 'content' => <<<'TXT'
                    You turn a user's request into ONE prompt for an image generator.
                    Describe subject, composition, layout, colours, style and the exact text that
                    must appear, quoting it verbatim in the user's own language.
                    If the user names an aspect ratio, repeat it.
                    Never invent phone numbers, addresses, logos or academic years the user did not give;
                    leave those out instead.
                    Reply with the prompt only — no preamble, no explanation, no markdown.
                    TXT],
                ['role' => 'user', 'content' => $request],
            ], $model);
        } catch (AiException) {
            return null;
        }

        $planned = trim($result['content']);

        return $planned === '' ? null : Str::limit($planned, 1200, '');
    }

    /**
     * Pull a canvas ratio out of plain language, so "rasio 16:9" or
     * "landscape" reaches the provider instead of being ignored.
     */
    public static function requestedAspectRatio(string $text): ?string
    {
        // Only canvas ratios the providers actually understand; this keeps
        // "jam 10:30" or a price from being read as a canvas.
        static $ratios = ['1:1', '4:3', '3:4', '3:2', '2:3', '16:9', '9:16', '21:9', '5:4', '4:5', '2:1', '1:2'];

        if (preg_match('/(?<![\d:.])(\d{1,2})\s*[:x×]\s*(\d{1,2})(?![\d:])/u', $text, $m) === 1) {
            $ratio = ((int) $m[1]).':'.((int) $m[2]);

            if (in_array($ratio, $ratios, true)) {
                return $ratio;
            }
        }

        $lower = Str::lower($text);

        return match (true) {
            str_contains($lower, 'landscape'), str_contains($lower, 'horizontal') => '16:9',
            str_contains($lower, 'potret'), str_contains($lower, 'portrait'),
            str_contains($lower, 'vertikal'), str_contains($lower, 'story') => '9:16',
            str_contains($lower, 'persegi'), str_contains($lower, 'square') => '1:1',
            default => null,
        };
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

    private function imageViaImagesEndpoint(string $model, string $prompt, ?string $aspectRatio = null): string
    {
        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'n' => 1,
        ];

        // Some gateways only accept 1:1 and answer 400 to any canvas control.
        if ($aspectRatio !== null && $aspectRatio !== '') {
            $payload['aspect_ratio'] = $aspectRatio;
        }

        $json = $this->decode($this->request()->post('/images/generations', $payload));

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
