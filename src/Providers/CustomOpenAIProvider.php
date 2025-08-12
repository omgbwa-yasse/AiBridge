<?php

namespace AiBridge\Providers;

use AiBridge\Contracts\ChatProviderContract;
use AiBridge\Contracts\EmbeddingsProviderContract;
use AiBridge\Contracts\ImageProviderContract;
use AiBridge\Contracts\AudioProviderContract;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Facade;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * CustomOpenAIProvider
 * Permet d'utiliser une API compatible OpenAI (proxy, Azure, self-host, etc.).
 * Supporte chat, embeddings, images, tts/stt (si endpoints fournis) + outils natifs comme OpenAIProvider.
 */
class CustomOpenAIProvider implements ChatProviderContract, EmbeddingsProviderContract, ImageProviderContract, AudioProviderContract
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
        $payload = [ 'model' => $options['model'] ?? ($options['deployment'] ?? 'gpt-like'), 'messages' => $messages ];
        if (isset($options['temperature'])) { $payload['temperature'] = $options['temperature']; }
        // Structured output
        if (!empty($options['response_format']) && $options['response_format'] === 'json') {
            $schema = $options['json_schema']['schema'] ?? [ 'type' => 'object' ];
            $payload['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => $options['json_schema'] ?? [ 'name' => 'auto_schema', 'schema' => $schema ]
            ];
        }
        // Native tools
        if (!empty($options['tools']) && is_array($options['tools'])) {
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
            if (!empty($options['tool_choice'])) { $payload['tool_choice'] = $options['tool_choice']; }
        }
        $res = $this->client()->post($this->endpoint('chat'), $payload)->json();
        if (isset($res['choices'][0]['message']['tool_calls'])) {
            $res['tool_calls'] = array_map(function ($tc) {
                return [
                    'id' => $tc['id'] ?? null,
                    'name' => $tc['function']['name'] ?? null,
                    'arguments' => json_decode($tc['function']['arguments'] ?? '{}', true) ?: [],
                ];
            }, $res['choices'][0]['message']['tool_calls']);
        }
        return $res ?? [];
    }

    public function stream(array $messages, array $options = []): \Generator
    {
        $payload = [ 'model' => $options['model'] ?? 'gpt-like', 'messages' => $messages, 'stream' => true ];
        $response = $this->client()->withOptions(['stream' => true])->post($this->endpoint('chat'), $payload);
        if (!method_exists($response, 'toPsrResponse')) { return; }
        $body = $response->toPsrResponse()->getBody();
        yield from $this->readSse($body);
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
