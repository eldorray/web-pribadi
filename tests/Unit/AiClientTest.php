<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\SiteSetting;
use App\Services\AiClient;
use App\Services\AiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiClientTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'sk-SECRET-TEST-KEY';

    protected function setUp(): void
    {
        parent::setUp();

        SiteSetting::set('ai_base_url', 'https://ai.test/v1/', 'text', 'ai');
        AiClient::setApiKey(self::KEY);
    }

    #[Test]
    public function api_key_stored_encrypted(): void
    {
        $stored = SiteSetting::get('ai_api_key');

        $this->assertNotSame(self::KEY, $stored);
        $this->assertStringNotContainsString(self::KEY, (string) $stored);
        $this->assertTrue(AiClient::hasApiKey());
    }

    #[Test]
    public function lists_model_ids(): void
    {
        Http::fake([
            'ai.test/v1/models' => Http::response([
                'data' => [['id' => 'gpt-4o-mini'], ['id' => 'o3-mini']],
            ]),
        ]);

        $models = (new AiClient)->listModels();

        $this->assertSame(['gpt-4o-mini', 'o3-mini'], $models);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://ai.test/v1/models'
            && $request->hasHeader('Authorization', 'Bearer '.self::KEY));
    }

    #[Test]
    public function chat_stream_collects_deltas(): void
    {
        $sse = "data: {\"choices\":[{\"delta\":{\"content\":\"Hal\"}}]}\n\n"
            ."data: {\"choices\":[{\"delta\":{\"content\":\"o\"}}]}\n\n"
            ."data: [DONE]\n\n";

        Http::fake(['ai.test/v1/chat/completions' => Http::response($sse)]);

        $deltas = [];
        $result = (new AiClient)->chat(
            [['role' => 'user', 'content' => 'Hai']],
            'gpt-4o-mini',
            null,
            function (string $chunk) use (&$deltas): void {
                $deltas[] = $chunk;
            },
        );

        $this->assertSame('Halo', $result['content']);
        $this->assertFalse($result['truncated']);
        $this->assertSame(['Hal', 'o'], $deltas);
    }

    #[Test]
    public function chat_can_stop_early_and_marks_truncated(): void
    {
        $sse = "data: {\"choices\":[{\"delta\":{\"content\":\"Satu\"}}]}\n\n"
            ."data: {\"choices\":[{\"delta\":{\"content\":\"Dua\"}}]}\n\n";

        Http::fake(['ai.test/v1/chat/completions' => Http::response($sse)]);

        $calls = 0;
        $result = (new AiClient)->chat(
            [['role' => 'user', 'content' => 'Hai']],
            'gpt-4o-mini',
            null,
            fn (string $chunk) => null,
            function () use (&$calls): bool {
                return ++$calls >= 1;
            },
        );

        $this->assertTrue($result['truncated']);
        $this->assertSame('Satu', $result['content']);
    }

    #[Test]
    public function effort_sent_only_when_given(): void
    {
        Http::fake(['ai.test/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'Halo']]],
        ])]);

        $client = new AiClient;
        $client->chat([['role' => 'user', 'content' => 'Hai']], 'o3-mini', 'high');
        $client->chat([['role' => 'user', 'content' => 'Hai']], 'o3-mini', null);

        $bodies = [];
        Http::assertSent(function (Request $request) use (&$bodies) {
            $bodies[] = $request->data();

            return true;
        });

        $this->assertSame('high', $bodies[0]['reasoning_effort']);
        $this->assertArrayNotHasKey('reasoning_effort', $bodies[1]);
    }

    #[Test]
    public function images_mode_decodes_b64(): void
    {
        SiteSetting::set('ai_image_mode', 'images', 'text', 'ai');

        Http::fake(['ai.test/v1/images/generations' => Http::response([
            'data' => [['b64_json' => base64_encode('PNGDATA')]],
        ])]);

        $this->assertSame('PNGDATA', (new AiClient)->generateImage('img-model', 'kucing biru'));
    }

    #[Test]
    public function chat_mode_decodes_data_url(): void
    {
        SiteSetting::set('ai_image_mode', 'chat', 'text', 'ai');

        Http::fake(['ai.test/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => [
                    'images' => [[
                        'image_url' => ['url' => 'data:image/png;base64,'.base64_encode('CHATIMG')],
                    ]],
                ],
            ]],
        ])]);

        $this->assertSame('CHATIMG', (new AiClient)->generateImage('img-model', 'kucing biru'));

        Http::assertSent(fn (Request $request) => ($request->data()['modalities'] ?? null) === ['image', 'text']);
    }

    #[Test]
    public function non_json_error_becomes_ai_exception(): void
    {
        Http::fake(['ai.test/v1/chat/completions' => Http::response('<html>Bad gateway</html>', 502)]);

        try {
            (new AiClient)->chat([['role' => 'user', 'content' => 'Hai']], 'gpt-4o-mini');
            $this->fail('Expected AiException.');
        } catch (AiException $e) {
            $this->assertStringContainsString('502', $e->getMessage());
            $this->assertStringNotContainsString(self::KEY, $e->getMessage());
        }
    }

    #[Test]
    public function provider_error_message_is_surfaced(): void
    {
        Http::fake(['ai.test/v1/chat/completions' => Http::response([
            'error' => ['message' => 'model not found'],
        ], 404)]);

        $this->expectException(AiException::class);
        $this->expectExceptionMessageMatches('/model not found/');

        (new AiClient)->chat([['role' => 'user', 'content' => 'Hai']], 'hilang');
    }

    #[Test]
    public function aspect_ratio_is_read_from_plain_language(): void
    {
        $this->assertSame('16:9', AiClient::requestedAspectRatio('buatkan flyer ppdb rasio 16:9'));
        $this->assertSame('9:16', AiClient::requestedAspectRatio('poster 9:16 untuk story'));
        $this->assertSame('16:9', AiClient::requestedAspectRatio('gambar landscape'));
        $this->assertSame('9:16', AiClient::requestedAspectRatio('versi potret'));
        $this->assertSame('1:1', AiClient::requestedAspectRatio('persegi saja'));
        $this->assertNull(AiClient::requestedAspectRatio('gambar kucing biru'));
        // A time or a price is not a canvas ratio.
        $this->assertNull(AiClient::requestedAspectRatio('rapat jam 10:30'));
    }

    #[Test]
    public function missing_api_key_fails_cleanly(): void
    {
        SiteSetting::set('ai_api_key', '', 'text', 'ai');

        $this->expectException(AiException::class);
        $this->expectExceptionMessageMatches('/API key/');

        (new AiClient)->listModels();
    }
}
