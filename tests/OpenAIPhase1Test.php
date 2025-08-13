<?php

namespace AiBridge\Tests;

use PHPUnit\Framework\TestCase;
use AiBridge\AiBridgeManager;
use AiBridge\Providers\OpenAIProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Facade;
use Illuminate\Container\Container;
use Illuminate\Http\Client\Factory as HttpFactory;

class OpenAIPhase1Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!Facade::getFacadeApplication()) {
            $app = new Container();
            $app->singleton('http', function () { return new HttpFactory(); });
            // @phpstan-ignore-next-line
            Facade::setFacadeApplication($app);
        }
        if (method_exists(Http::class, 'swap')) { Http::swap(new HttpFactory()); }
    }

    public function testResponsesApiChatBasic(): void
    {
        if (!class_exists(\Illuminate\Support\Facades\Http::class) || !method_exists(\Illuminate\Support\Facades\Http::class, 'fake')) {
            $this->markTestSkipped('Illuminate Http fake not available.');
        }
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'id' => 'resp_123',
                'model' => 'gpt-4o-mini',
                'output_text' => 'Hello from responses',
            ], 200),
        ]);
        $provider = new OpenAIProvider('test-key');
        $resp = $provider->chat([
            ['role' => 'system', 'content' => 'Be concise'],
            ['role' => 'user', 'content' => 'Say hi']
        ], [ 'api' => 'responses', 'model' => 'gpt-4o-mini' ]);
        $this->assertEquals('Hello from responses', $resp['output_text'] ?? null);
    }

    public function testEmbeddingsWithDimensions(): void
    {
        if (!class_exists(\Illuminate\Support\Facades\Http::class) || !method_exists(\Illuminate\Support\Facades\Http::class, 'fake')) {
            $this->markTestSkipped('Illuminate Http fake not available.');
        }
        Http::fake([
            'https://api.openai.com/v1/embeddings' => Http::response([
                'data' => [ ['embedding' => [0.1, 0.2]] ],
                'usage' => ['total_tokens' => 5]
            ], 200)
        ]);
        $provider = new OpenAIProvider('key');
        $out = $provider->embeddings(['hello'], ['dimensions' => 2, 'encoding_format' => 'float']);
        $this->assertCount(1, $out['embeddings'] ?? []);
        $this->assertEquals([0.1, 0.2], $out['embeddings'][0]);
    }

    public function testGenerateImageGptImage1Options(): void
    {
        if (!class_exists(\Illuminate\Support\Facades\Http::class) || !method_exists(\Illuminate\Support\Facades\Http::class, 'fake')) {
            $this->markTestSkipped('Illuminate Http fake not available.');
        }
        Http::fake([
            'https://api.openai.com/v1/images/generations' => Http::response([
                'data' => [ ['b64_json' => base64_encode('img')] ]
            ], 200)
        ]);
        $provider = new OpenAIProvider('k');
        $res = $provider->generateImage('a cat', [
            'model' => 'gpt-image-1',
            'size' => '512x512',
            'image_format' => 'png',
            'quality' => 'high',
            'moderation' => 'soft',
        ]);
        $this->assertIsArray($res['images'] ?? null);
    }

    public function testTextToSpeechMimeAndOptions(): void
    {
        if (!class_exists(\Illuminate\Support\Facades\Http::class) || !method_exists(\Illuminate\Support\Facades\Http::class, 'fake')) {
            $this->markTestSkipped('Illuminate Http fake not available.');
        }
        Http::fake([
            'https://api.openai.com/v1/audio/speech' => Http::response('AUDIOBYTES', 200)
        ]);
        $provider = new OpenAIProvider('k');
        $res = $provider->textToSpeech('hello', ['format' => 'wav', 'speed' => 1.1]);
        $this->assertEquals('audio/wav', $res['mime'] ?? null);
        $this->assertNotEmpty($res['audio'] ?? '');
    }

    public function testSpeechToTextWithOptions(): void
    {
        if (!class_exists(\Illuminate\Support\Facades\Http::class) || !method_exists(\Illuminate\Support\Facades\Http::class, 'fake')) {
            $this->markTestSkipped('Illuminate Http fake not available.');
        }
        // Create a temporary file to simulate audio
        $tmp = tempnam(sys_get_temp_dir(), 'aud');
        file_put_contents($tmp, 'FAKEAUDIO');

        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response([
                'text' => 'hello world'
            ], 200)
        ]);
        $provider = new OpenAIProvider('k');
        $out = $provider->speechToText($tmp, ['language' => 'en', 'prompt' => 'greeting', 'logprobs' => true]);
        $this->assertEquals('hello world', $out['text'] ?? null);
        @unlink($tmp);
    }

    public function testModelsListAndGetThroughManager(): void
    {
        if (!class_exists(\Illuminate\Support\Facades\Http::class) || !method_exists(\Illuminate\Support\Facades\Http::class, 'fake')) {
            $this->markTestSkipped('Illuminate Http fake not available.');
        }
        Http::fake([
            'https://api.openai.com/v1/models' => Http::response([
                'data' => [ ['id' => 'gpt-4o-mini'], ['id' => 'tts-1'] ]
            ], 200),
            'https://api.openai.com/v1/models/gpt-4o-mini' => Http::response([
                'id' => 'gpt-4o-mini', 'owned_by' => 'openai'
            ], 200),
        ]);
        $manager = new AiBridgeManager([ 'openai' => ['api_key' => 'test'] ]);
        $models = $manager->models('openai');
        $this->assertNotEmpty($models);
        $m = $manager->model('openai', 'gpt-4o-mini');
        $this->assertEquals('gpt-4o-mini', $m['id'] ?? null);
    }
}
