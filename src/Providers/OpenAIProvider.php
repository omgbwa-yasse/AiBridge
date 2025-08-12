<?php

namespace AiBridge\Providers;

use Illuminate\Support\Facades\Http;
use AiBridge\Contracts\ChatProviderContract;
use AiBridge\Contracts\EmbeddingsProviderContract;
use AiBridge\Contracts\ImageProviderContract;
use AiBridge\Contracts\AudioProviderContract;
use AiBridge\Support\JsonSchemaValidator;
use Psr\Http\Message\StreamInterface;

class OpenAIProvider implements ChatProviderContract, EmbeddingsProviderContract, ImageProviderContract, AudioProviderContract
{
    protected string $apiKey;
    protected string $chatEndpoint;
    protected string $embeddingsEndpoint = 'https://api.openai.com/v1/embeddings';
    protected string $imageEndpoint = 'https://api.openai.com/v1/images/generations';
    protected string $speechToTextEndpoint = 'https://api.openai.com/v1/audio/transcriptions';
    protected string $textToSpeechEndpoint = 'https://api.openai.com/v1/audio/speech';

    public function __construct(string $apiKey, string $chatEndpoint = 'https://api.openai.com/v1/chat/completions')
    {
        $this->apiKey = $apiKey;
        $this->chatEndpoint = $chatEndpoint;
    }

    protected function client()
    {
        $pending = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
        ])->acceptJson();
        // Optionally decorate via manager if resolved
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

    // ChatProviderContract
    public function chat(array $messages, array $options = []): array
    {
    $payload = $this->buildBasePayload($messages, $options);
    $schema = $this->applySchemaIfAny($payload, $options);
    $this->applyNativeToolsIfAny($payload, $options);

        $res = $this->client()->post($this->chatEndpoint, $payload);
        $data = $res->json() ?? [];
        // Provide normalized tool_calls if native function calling responded
        if (isset($data['choices'][0]['message']['tool_calls'])) {
            $data['tool_calls'] = array_map(function ($tc) {
                return [
                    'id' => $tc['id'] ?? null,
                    'name' => $tc['function']['name'] ?? null,
                    'arguments' => json_decode($tc['function']['arguments'] ?? '{}', true) ?: [],
                ];
            }, $data['choices'][0]['message']['tool_calls']);
        }
        if ($schema && isset($data['choices'][0]['message']['content'])) {
            $rawContent = $data['choices'][0]['message']['content'];
            $decoded = json_decode($rawContent, true);
            if (is_array($decoded)) {
                $errors = [];
                if (!JsonSchemaValidator::validate($decoded, $schema, $errors)) {
                    $data['schema_validation'] = [ 'valid' => false, 'errors' => $errors ];
                } else {
                    $data['schema_validation'] = [ 'valid' => true ];
                }
            }
        }
        return $data;
    }

    protected function buildBasePayload(array $messages, array $options): array
    {
        $payload = [ 'model' => $options['model'] ?? 'gpt-4o-mini', 'messages' => $messages ];
        if (isset($options['temperature'])) { $payload['temperature'] = $options['temperature']; }
        return $payload;
    }

    protected function applySchemaIfAny(array &$payload, array $options)
    {
        if (empty($options['response_format']) || $options['response_format'] !== 'json') { return null; }
        $schema = $options['json_schema']['schema'] ?? [ 'type' => 'object' ];
        $payload['response_format'] = [
            'type' => 'json_schema',
            'json_schema' => $options['json_schema'] ?? [ 'name' => 'auto_schema', 'schema' => $schema ]
        ];
        return $schema;
    }

    protected function applyNativeToolsIfAny(array &$payload, array $options): void
    {
        if (empty($options['tools']) || !is_array($options['tools'])) { return; }
        $payload['tools'] = array_map(function ($tool) {
            return [
                'type' => 'function',
                'function' => [
                    'name' => $tool['name'],
                    'description' => $tool['description'] ?? '',
                    'parameters' => $tool['parameters'] ?? ($tool['schema'] ?? ['type' => 'object','properties'=>[]]),
                ]
            ];
        }, $options['tools']);
        if (!empty($options['tool_choice'])) {
            $payload['tool_choice'] = $options['tool_choice'];
        }
    }

    public function stream(array $messages, array $options = []): \Generator
    {
        $payload = [ 'model' => $options['model'] ?? 'gpt-4o-mini', 'messages' => $messages, 'stream' => true ];
        $response = $this->client()->withOptions(['stream' => true])->post($this->chatEndpoint, $payload);
        if (!method_exists($response, 'toPsrResponse')) { return; }
        $body = $response->toPsrResponse()->getBody();
        foreach ($this->readSseStream($body) as $chunk) {
            yield $chunk;
        }
    }

    /**
     * @param StreamInterface $body
     * @return \Generator<string>
     */
    private function readSseStream(StreamInterface $body): \Generator
    {
        while (!$body->eof()) {
            $data = $body->read(2048);
            if ($data === '') { continue; }
            foreach (preg_split('/\r?\n/', $data) as $line) {
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

    public function supportsStreaming(): bool
    {
        return true;
    }

    // EmbeddingsProviderContract
    public function embeddings(array $inputs, array $options = []): array
    {
        $payload = [
            'model' => $options['model'] ?? 'text-embedding-3-small',
            'input' => $inputs,
        ];
        $res = $this->client()->post($this->embeddingsEndpoint, $payload)->json();
        return [
            'embeddings' => array_map(fn($d) => $d['embedding'] ?? [], $res['data'] ?? []),
            'usage' => $res['usage'] ?? [],
            'raw' => $res,
        ];
    }

    // ImageProviderContract
    public function generateImage(string $prompt, array $options = []): array
    {
        $payload = [
            'prompt' => $prompt,
            'model' => $options['model'] ?? 'dall-e-3',
            'size' => $options['size'] ?? '1024x1024',
            'response_format' => $options['response_format'] ?? 'url',
            'n' => $options['n'] ?? 1,
        ];
        $res = $this->client()->post($this->imageEndpoint, $payload)->json();
        return [
            'images' => $res['data'] ?? [],
            'raw' => $res,
        ];
    }

    // AudioProviderContract
    public function textToSpeech(string $text, array $options = []): array
    {
        $payload = [
            'model' => $options['model'] ?? 'tts-1',
            'input' => $text,
            'voice' => $options['voice'] ?? 'alloy',
            'format' => $options['format'] ?? 'mp3',
        ];
        $res = $this->client()->post($this->textToSpeechEndpoint, $payload);
        $b64 = base64_encode($res->body());
        return [ 'audio' => $b64, 'mime' => 'audio/mpeg' ];
    }

    public function speechToText(string $filePath, array $options = []): array
    {
        $res = $this->client()->attach('file', fopen($filePath, 'r'), basename($filePath))
            ->post($this->speechToTextEndpoint, [
                'model' => $options['model'] ?? 'whisper-1',
                'response_format' => $options['response_format'] ?? 'json'
            ])->json();
        return [ 'text' => $res['text'] ?? '', 'raw' => $res ];
    }
}
