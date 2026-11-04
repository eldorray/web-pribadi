<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\SiteSetting;
use App\Services\AiClient;
use App\Services\AiException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\Response as Psr7Response;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
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

        Http::assertSent(fn (Request $request): bool => $request['quality'] === 'auto'
            && $request['output_format'] === 'png'
            && is_string($request->header('Idempotency-Key')[0] ?? null));
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
    public function image_planner_receives_the_explicit_canvas_ratio(): void
    {
        Http::fake(['ai.test/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'Flyer lanskap final']]],
        ])]);

        $planned = (new AiClient)->planImagePrompt('Buat flyer PPDB', 'chat-basic', '16:9');

        $this->assertSame('Flyer lanskap final', $planned);
        Http::assertSent(function (Request $request): bool {
            $system = (string) ($request['messages'][0]['content'] ?? '');

            return str_contains($system, 'MUST be 16:9')
                && str_contains($system, 'blank placeholder panels');
        });
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

    /** @return callable(string): void */
    private function collector(array &$chunks): callable
    {
        return function (string $chunk) use (&$chunks): void {
            $chunks[] = $chunk;
        };
    }

    #[Test]
    public function connection_failures_surface_as_readable_errors(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));

        $calls = [
            'models' => fn () => (new AiClient)->listModels(),
            'chat' => fn () => (new AiClient)->chat([['role' => 'user', 'content' => 'x']], 'm'),
            'images' => fn () => (new AiClient)->generateImageResult('m', 'x'),
        ];

        foreach ($calls as $name => $call) {
            try {
                $call();
                $this->fail("{$name} should have thrown");
            } catch (AiException $e) {
                $this->assertStringContainsString('Tidak bisa menghubungi', $e->getMessage(), $name);
            }
        }
    }

    #[Test]
    public function chat_image_mode_connection_failure_is_readable(): void
    {
        SiteSetting::set('ai_image_mode', 'chat', 'text', 'ai');
        Http::fake(fn () => throw new ConnectionException('refused'));

        $this->expectException(AiException::class);

        (new AiClient)->generateImageResult('m', 'x');
    }

    #[Test]
    public function downloading_a_generated_image_failure_is_readable(): void
    {
        Http::fake([
            'ai.test/*' => Http::response(['data' => [['url' => 'https://cdn.test/a.png']]]),
            'cdn.test/*' => fn () => throw new ConnectionException('refused'),
        ]);

        $this->expectException(AiException::class);

        (new AiClient)->generateImageResult('m', 'x');
    }

    #[Test]
    public function planning_returns_null_when_the_provider_is_unreachable(): void
    {
        Http::fake(fn () => throw new ConnectionException('refused'));

        $this->assertNull((new AiClient)->planImagePrompt('gambar kucing', 'm'));
    }

    #[Test]
    public function plain_json_answer_to_a_stream_request_is_used(): void
    {
        Http::fake(['ai.test/*' => Http::response(['choices' => [['message' => ['content' => 'jawaban utuh']]]])]);

        $chunks = [];
        $result = (new AiClient)->chat([['role' => 'user', 'content' => 'x']], 'm', null, $this->collector($chunks));

        $this->assertSame('jawaban utuh', $result['content']);
        $this->assertSame(['jawaban utuh'], $chunks);
    }

    #[Test]
    public function html_answer_to_a_stream_request_is_an_error(): void
    {
        Http::fake(['ai.test/*' => Http::response('<html>portal login</html>')]);

        $this->expectException(AiException::class);
        $this->expectExceptionMessageMatches('/tidak dikenali/');

        (new AiClient)->chat([['role' => 'user', 'content' => 'x']], 'm', null, fn () => null);
    }

    #[Test]
    public function json_error_answer_to_a_stream_request_names_the_reason(): void
    {
        Http::fake(['ai.test/*' => Http::response(['error' => ['message' => 'kuota habis']])]);

        $this->expectException(AiException::class);
        $this->expectExceptionMessageMatches('/kuota habis/');

        (new AiClient)->chat([['role' => 'user', 'content' => 'x']], 'm', null, fn () => null);
    }

    #[Test]
    public function error_event_before_any_text_names_the_reason(): void
    {
        Http::fake(['ai.test/*' => Http::response('data: '.json_encode(['error' => ['message' => 'kuota habis']])."\n\n")]);

        $this->expectException(AiException::class);
        $this->expectExceptionMessageMatches('/kuota habis/');

        (new AiClient)->chat([['role' => 'user', 'content' => 'x']], 'm', null, fn () => null);
    }

    #[Test]
    public function error_event_after_some_text_keeps_the_text(): void
    {
        Http::fake(['ai.test/*' => Http::response(
            'data: '.json_encode(['choices' => [['delta' => ['content' => 'Sebagian']]]])."\n\n"
            .'data: '.json_encode(['error' => ['message' => 'putus']])."\n\n"
        )]);

        $result = (new AiClient)->chat([['role' => 'user', 'content' => 'x']], 'm', null, fn () => null);

        $this->assertSame(['content' => 'Sebagian', 'truncated' => true], $result);
    }

    #[Test]
    public function final_event_without_a_newline_is_not_lost(): void
    {
        Http::fake(['ai.test/*' => Http::response(
            'data: '.json_encode(['choices' => [['delta' => ['content' => 'Akhir']]]])
        )]);

        $result = (new AiClient)->chat([['role' => 'user', 'content' => 'x']], 'm', null, fn () => null);

        $this->assertSame('Akhir', $result['content']);
    }

    #[Test]
    public function stop_is_honoured_during_silent_keep_alive_lines(): void
    {
        Http::fake(['ai.test/*' => Http::response(
            ": PROCESSING\n\n".'data: '.json_encode(['choices' => [['delta' => ['content' => 'Telat']]]])."\n\n"
        )]);

        $result = (new AiClient)->chat([['role' => 'user', 'content' => 'x']], 'm', null, fn () => null, fn () => true);

        $this->assertSame(['content' => '', 'truncated' => true], $result);
    }

    #[Test]
    public function connection_dropped_mid_stream_keeps_the_text_received(): void
    {
        $this->fakeStreamBody('data: '.json_encode(['choices' => [['delta' => ['content' => 'Sebagian']]]])."\n\n");

        $result = (new AiClient)->chat([['role' => 'user', 'content' => 'x']], 'm', null, fn () => null);

        $this->assertSame(['content' => 'Sebagian', 'truncated' => true], $result);
    }

    #[Test]
    public function connection_dropped_before_any_text_is_an_error(): void
    {
        $this->fakeStreamBody('');

        $this->expectException(AiException::class);
        $this->expectExceptionMessageMatches('/Tidak bisa menghubungi/');

        (new AiClient)->chat([['role' => 'user', 'content' => 'x']], 'm', null, fn () => null);
    }

    /** A body that hands out $first, then fails the way a dropped socket does. */
    private function fakeStreamBody(string $first): void
    {
        $served = false;

        $body = FnStream::decorate(Utils::streamFor(''), [
            'eof' => fn () => false,
            'read' => function () use (&$served, $first) {
                if (! $served && $first !== '') {
                    $served = true;

                    return $first;
                }

                throw new RuntimeException('Unable to read from stream');
            },
        ]);

        Http::fake(['ai.test/*' => fn () => Create::promiseFor(new Psr7Response(200, [], $body))]);
    }
}
