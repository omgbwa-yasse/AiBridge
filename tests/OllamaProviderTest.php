<?php

declare(strict_types=1);

namespace AiBridge\Tests;

use PHPUnit\Framework\TestCase;
use AiBridge\AiBridgeManager;
use AiBridge\Providers\OllamaProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Factory as HttpFactory;

class OllamaProviderTest extends TestCase
{
    private const ENDPOINT = 'http://localhost:11434';

    protected function setUp(): void
    {
        parent::setUp();
        if (method_exists(Http::class, 'swap')) {
            Http::swap(new HttpFactory());
        }
    }

    public function testManagerRegistersOllama(): void
    {
    $manager = new AiBridgeManager([
            'ollama' => ['endpoint' => self::ENDPOINT]
        ]);
        $provider = $manager->provider('ollama');
        $this->assertInstanceOf(OllamaProvider::class, $provider);
    }

    public function testEmbeddingsMethodExists(): void
    {
        $provider = new OllamaProvider(self::ENDPOINT);
        $this->assertTrue(method_exists($provider, 'embeddings'));
    }

    public function testSupportsStreaming(): void
    {
        $provider = new OllamaProvider(self::ENDPOINT);
        $this->assertTrue($provider->supportsStreaming());
    }

    public function testChatSimpleConversationMocked(): void
    {
        if (!class_exists(Http::class) || !method_exists(Http::class, 'fake')) {
            $this->markTestSkipped('Illuminate Http fake not available.');
        }

        Http::fake([
            self::ENDPOINT.'*' => Http::response([
                'model' => 'llama2',
                'message' => [ 'role' => 'assistant', 'content' => 'Bonjour utilisateur!' ],
                'done' => true,
            ], 200)
        ]);

        $provider = new OllamaProvider(self::ENDPOINT);
        $resp = $provider->chat([
            ['role' => 'user', 'content' => 'Salut']
        ]);
        $this->assertEquals('Bonjour utilisateur!', $resp['message']['content'] ?? null);
    }

    public function testChatStructuredJsonAddsFormat(): void
    {
        if (!class_exists(Http::class) || !method_exists(Http::class, 'fake')) {
            $this->markTestSkipped('Illuminate Http fake not available.');
        }
        $formatSeen = false;
        Http::fake([
            self::ENDPOINT.'*' => function ($request) use (&$formatSeen) {
                $body = json_decode($request->body(), true) ?: [];
                if (!empty($body['format']) && $body['format'] === 'json') { $formatSeen = true; }
                return Http::response([
                    'model' => 'llama2',
                    'message' => [ 'role' => 'assistant', 'content' => json_encode(['ok' => true]) ],
                    'done' => true,
                ], 200);
            }
        ]);
        $provider = new OllamaProvider(self::ENDPOINT);
        $resp = $provider->chat([
            ['role' => 'user', 'content' => 'Réponds en JSON']
        ], [ 'response_format' => 'json' ]);
        $this->assertTrue($formatSeen, 'Expected format json flag in request payload');
        $this->assertStringContainsString('ok', $resp['message']['content'] ?? '');
    }
}

