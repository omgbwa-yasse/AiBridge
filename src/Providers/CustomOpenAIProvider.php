<?php

namespace AiBridge\Providers;

use AiBridge\Contracts\ChatProviderContract;
use AiBridge\Contracts\EmbeddingsProviderContract;
use AiBridge\Contracts\ImageProviderContract;
use AiBridge\Contracts\AudioProviderContract;
use AiBridge\Contracts\ModelsProviderContract;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Facade;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * CustomOpenAIProvider
 * Permet d'utiliser une API compatible OpenAI (proxy, Azure, self-host, etc.).
 * Supporte chat, embeddings, images, tts/stt (si endpoints fournis) + outils natifs comme OpenAIProvider.
 */
class CustomOpenAIProvider implements ChatProviderContract, EmbeddingsProviderContract, ImageProviderContract, AudioProviderContract, ModelsProviderContract
{
    protected string $apiKey;
    protected string $baseUrl;
    protected array $paths;
    protected string $authHeader;
    protected string $authPrefix;
    protected array $extraHeaders;

    public function __construct(string $apiKey, string $baseUrl, array $paths, string $authHeader = 'Authorization', string $authPrefix = 'Bearer ', array $extraHeaders = [])
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->paths = $paths;
        $this->authHeader = $authHeader;
        $this->authPrefix = $authPrefix;
        $this->extraHeaders = $extraHeaders;
    }

    protected function endpoint(string $key): string
    {
        $path = $this->paths[$key] ?? '';
        return $this->baseUrl . $path;
    }

    protected function client()
    {
        $headers = array_merge([
            $this->authHeader => $this->authPrefix . $this->apiKey,
        ], $this->extraHeaders);
        if (class_exists(Facade::class) && Facade::getFacadeApplication() && class_exists(Http::class)) {
            $pending = Http::withHeaders($headers)->acceptJson();
        } else {
            static $factory; if (!$factory) { $factory = new HttpFactory(); }
            $pending = $factory->withHeaders($headers)->acceptJson();
        }
        if (function_exists('app')) {
            try {
                $manager = \call_user_func('app', 'AiBridge');
                if (is_object($manager) && method_exists($manager, 'decorateHttp')) {
                    return $manager->decorateHttp($pending);
                }
            } catch (\Throwable $e) { /* ignore outside laravel */ }
        }
        return $pending;
    }

    // Chat
    public function chat(array $messages, array $options = []): array
    {
        $payload = $this->buildChatPayload($messages, $options);
        $res = $this->client()->post($this->endpoint('chat'), $payload)->json();
        $this->normalizeToolCallsOnResponse($res);
        return $res ?? [];
    }

    protected function buildChatPayload(array $messages, array $options): array
    {
        $payload = [
            'model' => $options['model'] ?? ($options['deployment'] ?? 'gpt-like'),
            'messages' => $messages,
        ];
        $this->applySamplingOptions($payload, $options);
        $this->applyResponseFormatOptions($payload, $options);
        $this->applyToolsOptions($payload, $options);
        return $payload;
    }

    protected function applySamplingOptions(array &$payload, array $options): void
    {
        foreach (['temperature','top_p','max_tokens','frequency_penalty','presence_penalty','stop','seed','user'] as $k) {
            if (array_key_exists($k, $options)) {
                $payload[$k] = $options[$k];
            }
        }
    }

    protected function applyResponseFormatOptions(array &$payload, array $options): void
    {
        if (!empty($options['response_format']) && $options['response_format'] === 'json') {
            $schema = $options['json_schema']['schema'] ?? [ 'type' => 'object' ];
            $payload['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => $options['json_schema'] ?? [ 'name' => 'auto_schema', 'schema' => $schema ]
            ];
        }
    }

    protected function applyToolsOptions(array &$payload, array $options): void
    {
        if (empty($options['tools']) || !is_array($options['tools'])) { return; }
        $payload['tools'] = array_map(function ($tool) {
            return [
                'type' => 'function',
                'function' => [
                    'name' => $tool['name'],
                    'description' => $tool['description'] ?? '',
                    'parameters' => $tool['parameters'] ?? ($tool['schema'] ?? ['type'=>'object','properties'=>[]]),
                ]
            ];
        }, $options['tools']);
        if (!empty($options['tool_choice'])) {
            $payload['tool_choice'] = $options['tool_choice'];
        }
    }

    protected function normalizeToolCallsOnResponse(?array &$res): void
    {
        if (!is_array($res) || !isset($res['choices'][0]['message']['tool_calls'])) { return; }
        $res['tool_calls'] = array_map(function ($tc) {
            return [
                'id' => $tc['id'] ?? null,
                'name' => $tc['function']['name'] ?? null,
                'arguments' => json_decode($tc['function']['arguments'] ?? '{}', true) ?: [],
            ];
        }, $res['choices'][0]['message']['tool_calls']);
    }

    public function stream(array $messages, array $options = []): \Generator
    {
        $payload = [ 'model' => $options['model'] ?? 'gpt-like', 'messages' => $messages, 'stream' => true ];
        $response = $this->client()->withOptions(['stream' => true])->post($this->endpoint('chat'), $payload);
        if (!method_exists($response, 'toPsrResponse')) { return; }
        $body = $response->toPsrResponse()->getBody();
        yield from $this->readSse($body);
    }

    /**
     * Yield structured events similar to OpenAIProvider::streamEvents
     * Each event: ['type' => 'delta'|'end', 'data' => string|null]
     */
    public function streamEvents(array $messages, array $options = []): \Generator
    {
        $payload = [ 'model' => $options['model'] ?? 'gpt-like', 'messages' => $messages, 'stream' => true ];
        $response = $this->client()->withOptions(['stream' => true])->post($this->endpoint('chat'), $payload);
        if (!method_exists($response, 'toPsrResponse')) { return; }
        $body = $response->toPsrResponse()->getBody();
        foreach ($this->readSse($body) as $delta) {
            yield ['type' => 'delta', 'data' => $delta];
        }
        yield ['type' => 'end', 'data' => null];
    }

    protected function readSse($body): \Generator
    {
        while (!$body->eof()) {
            $chunk = $body->read(2048);
            if ($chunk === '') { continue; }
            foreach (preg_split('/\r?\n/', $chunk) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, ':') || !str_starts_with($line, 'data:')) { continue; }
                $json = trim(substr($line, 5));
                if ($json === '[DONE]') { return; }
                $decoded = json_decode($json, true);
                $delta = $decoded['choices'][0]['delta']['content'] ?? null;
                if ($delta !== null) { yield $delta; }
            }
        }
    }

    public function supportsStreaming(): bool { return true; }

    // Models
    public function listModels(): array
    {
        $url = rtrim($this->baseUrl, '/') . $this->modelsPath();
        return $this->client()->get($url)->json() ?? [];
    }

    public function getModel(string $id): array
    {
        $url = rtrim($this->baseUrl, '/') . $this->modelsPath() . '/' . urlencode($id);
        return $this->client()->get($url)->json() ?? [];
    }

    protected function modelsPath(): string
    {
        // If a custom path is provided, prefer it
        if (!empty($this->paths['models'])) {
            return $this->paths['models'];
        }
        // If baseUrl already contains '/v1', use '/models', else use '/v1/models'
        $b = rtrim($this->baseUrl, '/');
        if (preg_match('~/v\d+(?:$|/)~', $b)) {
            return '/models';
        }
        return '/v1/models';
    }

    // Embeddings
    public function embeddings(array $inputs, array $options = []): array
    {
        $payload = [ 'model' => $options['model'] ?? 'embedding-model', 'input' => $inputs ];
        $res = $this->client()->post($this->endpoint('embeddings'), $payload)->json();
        return [
            'embeddings' => array_map(fn($d) => $d['embedding'] ?? [], $res['data'] ?? []),
            'usage' => $res['usage'] ?? [],
            'raw' => $res,
        ];
    }

    // Image
    public function generateImage(string $prompt, array $options = []): array
    {
        $payload = [ 'prompt' => $prompt, 'model' => $options['model'] ?? 'image-model', 'n' => 1 ];
        $res = $this->client()->post($this->endpoint('image'), $payload)->json();
        return [ 'images' => $res['data'] ?? [], 'raw' => $res ];
    }

    // Audio
    public function textToSpeech(string $text, array $options = []): array
    {
        $payload = [ 'model' => $options['model'] ?? 'tts-model', 'input' => $text, 'voice' => $options['voice'] ?? 'alloy', 'format' => $options['format'] ?? 'mp3' ];
        $res = $this->client()->post($this->endpoint('tts'), $payload);
        return [ 'audio' => base64_encode($res->body()), 'mime' => 'audio/mpeg' ];
    }

    public function speechToText(string $filePath, array $options = []): array
    {
        $res = $this->client()->attach('file', fopen($filePath, 'r'), basename($filePath))
            ->post($this->endpoint('stt'), [ 'model' => $options['model'] ?? 'stt-model', 'response_format' => 'json' ])
            ->json();
        return [ 'text' => $res['text'] ?? '', 'raw' => $res ];
    }
}
