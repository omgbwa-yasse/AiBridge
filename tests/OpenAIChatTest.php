<?php

namespace AiBridge\Tests;

use PHPUnit\Framework\TestCase;
use AiBridge\AiBridgeManager;
use AiBridge\Providers\OpenAIProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Facade;
use Illuminate\Container\Container;
use Illuminate\Http\Client\Factory as HttpFactory;

class OpenAIChatTest extends TestCase
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
    public function testManagerRegistersOpenAI(): void
    {
    $manager = new AiBridgeManager([
            'openai' => ['api_key' => 'test-key']
        ]);
        $provider = $manager->provider('openai');
        $this->assertInstanceOf(OpenAIProvider::class, $provider);
    }

    public function testEmbeddingsMethodExists(): void
    {
        $provider = new OpenAIProvider('dummy');
        $this->assertTrue(method_exists($provider, 'embeddings'));
    }

    public function testSchemaValidationFlagOnJsonResponse(): void
    {
        // Skip if Http facade fake isn't available
        if (!class_exists(\Illuminate\Support\Facades\Http::class) || !method_exists(\Illuminate\Support\Facades\Http::class, 'fake')) {
            $this->markTestSkipped('Illuminate Http fake not available.');
        }
        $schema = [
            'name' => 'user_profile',
            'schema' => [
                'type' => 'object',
                'required' => ['name','age'],
                'properties' => [
                    'name' => ['type' => 'string'],
                    'age' => ['type' => 'number']
                ]
            ]
        ];
        // Fake OpenAI chat response with valid JSON matching the schema
        \Illuminate\Support\Facades\Http::fake([
            'https://api.openai.com/v1/chat/completions' => \Illuminate\Support\Facades\Http::response([
                'choices' => [[ 'message' => [ 'content' => json_encode(['name' => 'Ada', 'age' => 27]) ]]]
            ], 200)
        ]);
        $provider = new OpenAIProvider('test-key');
    $resp = $provider->chat([
            ['role' => 'user', 'content' => 'Give JSON']
    ], [ 'api' => 'chat', 'response_format' => 'json', 'json_schema' => $schema ]);
    $this->assertTrue($resp['schema_validation']['valid'] ?? false);
    }

    public function testNativeToolCallsAreNormalized(): void
    {
        if (!class_exists(\Illuminate\Support\Facades\Http::class) || !method_exists(\Illuminate\Support\Facades\Http::class, 'fake')) {
            $this->markTestSkipped('Illuminate Http fake not available.');
        }
        // Fake OpenAI tool_calls message structure
        $toolCall = [
            'id' => 'call_123',
            'type' => 'function',
            'function' => [ 'name' => 'getWeather', 'arguments' => json_encode(['city' => 'Paris']) ]
        ];
        \Illuminate\Support\Facades\Http::fake([
            'https://api.openai.com/v1/chat/completions' => \Illuminate\Support\Facades\Http::response([
                'choices' => [[ 'message' => [ 'content' => null, 'tool_calls' => [ $toolCall ] ]]]
            ], 200)
        ]);
        $provider = new OpenAIProvider('test-key');
    $resp = $provider->chat([
            ['role' => 'user', 'content' => 'weather please']
    ], [ 'api' => 'chat',
            'tools' => [[
                'name' => 'getWeather',
                'description' => 'Get weather for a city',
                'parameters' => [ 'type' => 'object', 'properties' => ['city' => ['type' => 'string']], 'required' => ['city'] ]
            ]],
            'tool_choice' => 'auto'
        ]);
        $this->assertIsArray($resp['tool_calls'] ?? null);
        $this->assertEquals('getWeather', $resp['tool_calls'][0]['name'] ?? null);
        $this->assertEquals('Paris', $resp['tool_calls'][0]['arguments']['city'] ?? null);
    }
}
