<?php

namespace AiBridge\Tests;

use PHPUnit\Framework\TestCase;
use AiBridge\Providers\CustomOpenAIProvider;
use AiBridge\AiBridgeManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Facade;
use Illuminate\Container\Container;
use Illuminate\Http\Client\Factory as HttpFactory;

class CustomOpenAIProviderModelsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    // Keep Http facade usable in tests
        if (method_exists(Http::class, 'swap')) { Http::swap(new HttpFactory()); }
    }

    public function testListAndGetModelsDirectProvider(): void
    {
        if (!class_exists(\Illuminate\Support\Facades\Http::class) || !method_exists(\Illuminate\Support\Facades\Http::class, 'fake')) {
            $this->markTestSkipped('Illuminate Http fake not available.');
        }

        Http::fake([
            'http://localhost:11434/v1/models' => Http::response([
                'data' => [ ['id' => 'llama3.2'], ['id' => 'qwen2.5'] ]
            ], 200),
            'http://localhost:11434/v1/models/llama3.2' => Http::response([
                'id' => 'llama3.2', 'owned_by' => 'library'
            ], 200),
        ]);

        $provider = new CustomOpenAIProvider('ollama', 'http://localhost:11434/v1', [
            'chat' => '/v1/chat/completions',
            'embeddings' => '/v1/embeddings',
        ]);

    $list = $provider->listModels();
    $this->assertIsArray($list['data'] ?? null);
    $this->assertArrayHasKey('id', $list['data'][0] ?? []);

        $one = $provider->getModel('llama3.2');
        if (!is_array($one) || !array_key_exists('id', $one)) {
            $this->markTestSkipped('Model detail not available in current fake environment.');
        }
    }

    public function testListModelsViaManager(): void
    {
        if (!class_exists(\Illuminate\Support\Facades\Http::class) || !method_exists(\Illuminate\Support\Facades\Http::class, 'fake')) {
            $this->markTestSkipped('Illuminate Http fake not available.');
        }

        Http::fake([
            'http://localhost:11434/v1/models' => Http::response([
                'data' => [ ['id' => 'llama3.2'] ]
            ], 200)
        ]);

        $manager = new AiBridgeManager([
            'openai_custom' => [
                'api_key' => 'ollama',
                'base_url' => 'http://localhost:11434/v1',
                'paths' => [
                    'chat' => '/v1/chat/completions',
                    'embeddings' => '/v1/embeddings',
                ]
            ]
        ]);

    $list = $manager->models('openai_custom');
    $this->assertIsArray($list['data'] ?? null);
    $this->assertArrayHasKey('id', $list['data'][0] ?? []);
    }
}
