<?php

namespace AiBridge\Providers;

use Illuminate\Support\Facades\Http;
use AiBridge\Contracts\ChatProviderContract;
use AiBridge\Contracts\ModelsProviderContract;
use AiBridge\Contracts\EmbeddingsProviderContract;
use AiBridge\Contracts\ImageProviderContract;
use AiBridge\Contracts\AudioProviderContract;
use AiBridge\Support\JsonSchemaValidator;
use Psr\Http\Message\StreamInterface;
use AiBridge\Support\DocumentAttachmentMapper;

class OpenAIProvider implements ChatProviderContract, EmbeddingsProviderContract, ImageProviderContract, AudioProviderContract, ModelsProviderContract
{
    protected string $apiKey;
    protected string $chatEndpoint;
    protected string $responsesEndpoint = 'https://api.openai.com/v1/responses';
    protected string $modelsEndpoint = 'https://api.openai.com/v1/models';
    protected string $embeddingsEndpoint = 'https://api.openai.com/v1/embeddings';
    protected string $imageEndpoint = 'https://api.openai.com/v1/images/generations';
    protected string $imageEditsEndpoint = 'https://api.openai.com/v1/images/edits';
    protected string $imageVariationsEndpoint = 'https://api.openai.com/v1/images/variations';
    protected string $speechToTextEndpoint = 'https://api.openai.com/v1/audio/transcriptions';
    protected string $speechTranslationsEndpoint = 'https://api.openai.com/v1/audio/translations';
    protected string $textToSpeechEndpoint = 'https://api.openai.com/v1/audio/speech';
    protected string $filesEndpoint = 'https://api.openai.com/v1/files';
    protected string $vectorStoresEndpoint = 'https://api.openai.com/v1/vector_stores';

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

    /**
     * Apply optional OpenAI-Organization / OpenAI-Project headers if passed in $options.
     * Accepts keys: 'organization' and 'project'.
     */
    protected function withOrgProjectHeaders($pending, array $options)
    {
        $headers = [];
        if (!empty($options['organization'])) { $headers['OpenAI-Organization'] = $options['organization']; }
        if (!empty($options['project'])) { $headers['OpenAI-Project'] = $options['project']; }
        return $headers ? $pending->withHeaders($headers) : $pending;
    }

    // ChatProviderContract
    public function chat(array $messages, array $options = []): array
    {
        // Opt-in path to Responses API
    if (($options['api'] ?? null) !== 'chat') {
            $payload = $this->buildResponsesPayload($messages, $options);
            // If documents are provided and file_search requested, attach resources
            $payload = $this->maybeAttachFileSearch($payload, $options);
            $pending = $this->withOrgProjectHeaders($this->client(), $options);
            $res = $pending->post($this->responsesEndpoint, $payload);
            $data = $res->json() ?? [];
            // Optional JSON Schema validation when structured outputs requested
            if (!empty($options['response_format']) && $options['response_format'] === 'json') {
                $schema = $options['json_schema']['schema'] ?? [ 'type' => 'object' ];
                $rawContent = $data['output_text'] ?? null;
                if (is_string($rawContent)) {
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
            }
            return $data;
        }

        $payload = $this->buildBasePayload($messages, $options);
        $schema = $this->applySchemaIfAny($payload, $options);
        $this->applyNativeToolsIfAny($payload, $options);

        $pending = $this->withOrgProjectHeaders($this->client(), $options);
        $res = $pending->post($this->chatEndpoint, $payload);
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
        if (isset($options['top_p'])) { $payload['top_p'] = $options['top_p']; }
        if (isset($options['max_tokens'])) { $payload['max_tokens'] = $options['max_tokens']; }
        if (isset($options['frequency_penalty'])) { $payload['frequency_penalty'] = $options['frequency_penalty']; }
        if (isset($options['presence_penalty'])) { $payload['presence_penalty'] = $options['presence_penalty']; }
        if (isset($options['stop'])) { $payload['stop'] = $options['stop']; }
        if (isset($options['seed'])) { $payload['seed'] = $options['seed']; }
        if (isset($options['user'])) { $payload['user'] = $options['user']; }
    if (isset($options['logprobs'])) { $payload['logprobs'] = $options['logprobs']; }
    if (isset($options['top_logprobs'])) { $payload['top_logprobs'] = $options['top_logprobs']; }
        return $payload;
    }

    /** Build a minimal Responses payload from chat-like messages */
    protected function buildResponsesPayload(array $messages, array $options): array
    {
        // Expand inline texts from attachments into message content
        foreach ($messages as $i => $m) {
            $atts = $m['attachments'] ?? [];
            if (!empty($atts)) {
                $inline = DocumentAttachmentMapper::extractInlineTexts($atts);
                if (!empty($inline)) {
                    $messages[$i]['content'] = rtrim((string)($m['content'] ?? '')) . "\n\n" . implode("\n\n", $inline);
                }
                unset($messages[$i]['attachments']);
            }
        }
        $model = $options['model'] ?? 'gpt-4o-mini';
        $instructions = [];
        $parts = [];
        foreach ($messages as $m) {
            $role = $m['role'] ?? 'user';
            $content = is_array($m['content'] ?? null) ? json_encode($m['content']) : ($m['content'] ?? '');
            if ($role === 'system') { $instructions[] = $content; continue; }
            // Concatenate non-system into a flat text transcript
            $parts[] = sprintf('%s: %s', $role, $content);
        }
        $payload = [
            'model' => $model,
            'input' => implode("\n", $parts),
        ];
        if (!empty($instructions)) { $payload['instructions'] = implode("\n\n", $instructions); }
        // common generation options
        foreach (['temperature','top_p','max_tokens','seed','stop','user','service_tier','prompt_cache_key','safety_identifier'] as $k) {
            if (isset($options[$k])) { $payload[$k] = $options[$k]; }
        }
        // logprobs (if provided/compatible)
        if (isset($options['logprobs'])) { $payload['logprobs'] = $options['logprobs']; }
        if (isset($options['top_logprobs'])) { $payload['top_logprobs'] = $options['top_logprobs']; }
        // tools mapping (function calling remains similar enough)
        if (!empty($options['tools'])) {
            $payload['tools'] = array_map(function ($tool) {
                // Pass-through non-function built-ins (e.g., web_search, file_search) for Responses
                if (!empty($tool['type']) && $tool['type'] !== 'function') {
                    return $tool;
                }
                return [
                    'type' => 'function',
                    'function' => [
                        'name' => $tool['name'],
                        'description' => $tool['description'] ?? '',
                        'parameters' => $tool['parameters'] ?? ($tool['schema'] ?? ['type' => 'object','properties'=>[]]),
                    ],
                ];
            }, $options['tools']);
            if (!empty($options['tool_choice'])) { $payload['tool_choice'] = $options['tool_choice']; }
        }
        // structured outputs via json_schema (compatible flag name)
        if (!empty($options['response_format']) && $options['response_format'] === 'json') {
            $schema = $options['json_schema']['schema'] ?? [ 'type' => 'object' ];
            $payload['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => $options['json_schema'] ?? [ 'name' => 'auto_schema', 'schema' => $schema ],
            ];
        }
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
        // Responses API streaming
    if (($options['api'] ?? null) !== 'chat') {
            $payload = $this->buildResponsesPayload($messages, $options);
            $payload['stream'] = true;
            $payload = $this->maybeAttachFileSearch($payload, $options);
            $pending = $this->withOrgProjectHeaders($this->client(), $options);
            $response = $pending->withOptions(['stream' => true])->post($this->responsesEndpoint, $payload);
            if (!method_exists($response, 'toPsrResponse')) { return; }
            $body = $response->toPsrResponse()->getBody();
            foreach ($this->readResponsesSse($body) as $chunk) { yield $chunk; }
            return;
        }

        $payload = $this->buildBasePayload($messages, $options);
        $payload['stream'] = true;
        $pending = $this->withOrgProjectHeaders($this->client(), $options);
        $response = $pending->withOptions(['stream' => true])->post($this->chatEndpoint, $payload);
        if (!method_exists($response, 'toPsrResponse')) { return; }
        $body = $response->toPsrResponse()->getBody();
        foreach ($this->readSseStream($body) as $chunk) {
            yield $chunk;
        }
    }

    /**
     * Yield structured events: ['type'=>'delta'|'end','data'=>string|array]
     */
    public function streamEvents(array $messages, array $options = []): \Generator
    {
    if (($options['api'] ?? null) !== 'chat') {
            $payload = $this->buildResponsesPayload($messages, $options);
            $payload['stream'] = true;
            $pending = $this->withOrgProjectHeaders($this->client(), $options);
            $response = $pending->withOptions(['stream' => true])->post($this->responsesEndpoint, $payload);
            if (!method_exists($response, 'toPsrResponse')) { return; }
            $body = $response->toPsrResponse()->getBody();
            while (!$body->eof()) {
                $data = $body->read(2048);
                if ($data === '') { continue; }
                foreach (preg_split('/\r?\n/', $data) as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, ':') || !str_starts_with($line, 'data:')) { continue; }
                    $json = trim(substr($line, 5));
                    if ($json === '[DONE]') { yield ['type' => 'end', 'data' => null]; return; }
                    $decoded = json_decode($json, true);
                    // Heuristics for Responses SSE events
                    $evtType = $decoded['type'] ?? null;
                    if ($evtType === 'response.completed') { yield ['type' => 'end', 'data' => null]; return; }
                    $text = $decoded['delta'] ?? ($decoded['output_text'] ?? null);
                    if (is_array($text)) { $text = null; }
                    if ($text !== null && $text !== '') { yield ['type' => 'delta', 'data' => $text]; }
                }
            }
            return;
        }

        $payload = $this->buildBasePayload($messages, $options);
        $payload['stream'] = true;
        $pending = $this->withOrgProjectHeaders($this->client(), $options);
        $response = $pending->withOptions(['stream' => true])->post($this->chatEndpoint, $payload);
        if (!method_exists($response, 'toPsrResponse')) { return; }
        $body = $response->toPsrResponse()->getBody();
        while (!$body->eof()) {
            $data = $body->read(2048);
            if ($data === '') { continue; }
            foreach (preg_split('/\r?\n/', $data) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, ':') || !str_starts_with($line, 'data:')) { continue; }
                $json = trim(substr($line, 5));
                if ($json === '[DONE]') { yield ['type' => 'end', 'data' => null]; return; }
                $decoded = json_decode($json, true);
                $delta = $decoded['choices'][0]['delta']['content'] ?? null;
                if ($delta !== null) { yield ['type' => 'delta', 'data' => $delta]; }
            }
        }
    }

    /**
     * @param StreamInterface $body
     * @return \Generator<string>
     */
    protected function readSseStream(StreamInterface $body): \Generator
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

    /**
     * SSE reader for Responses API that yields raw text deltas when detectable.
     * @param StreamInterface $body
     * @return \Generator<string>
     */
    protected function readResponsesSse(StreamInterface $body): \Generator
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
                $evtType = $decoded['type'] ?? null;
                if ($evtType === 'response.completed') { return; }
                $text = $decoded['delta'] ?? ($decoded['output_text'] ?? null);
                if (is_array($text)) { $text = null; }
                if ($text !== null && $text !== '') { yield $text; }
            }
        }
    }

    public function supportsStreaming(): bool
    {
        return true;
    }

    /**
     * If options request built-in file_search and provide vector_store_id or files, attach resources payload.
     * Non-destructive: returns payload unchanged if nothing to do.
     */
    protected function maybeAttachFileSearch(array $payload, array $options): array
    {
        $tools = $options['tools'] ?? [];
        $wantsFileSearch = false;
        foreach ($tools as $t) {
            if (($t['type'] ?? null) === 'file_search') { $wantsFileSearch = true; break; }
        }
        if (!$wantsFileSearch) { return $payload; }
        // Attach tool as-is (already done in buildResponsesPayload), just ensure resources block exists
        if (!empty($options['vector_store_id'])) {
            $payload['resources']['file_search']['vector_store_ids'] = [ $options['vector_store_id'] ];
            return $payload;
        }
        if (!empty($options['file_ids']) && is_array($options['file_ids'])) {
            $payload['resources']['file_search']['file_ids'] = array_values($options['file_ids']);
            return $payload;
        }
        // If local files are passed for convenience, upload them quickly to Files API and attach file_ids.
        if (!empty($options['files']) && is_array($options['files'])) {
            $fileIds = [];
            foreach ($options['files'] as $path) {
                if (!is_string($path) || !file_exists($path)) { continue; }
                $res = $this->withOrgProjectHeaders($this->client(), $options)
                    ->asMultipart()
                    ->attach('file', fopen($path, 'r'), basename($path))
                    ->post($this->filesEndpoint, [ 'purpose' => 'assistants' ])
                    ->json();
                if (!empty($res['id'])) { $fileIds[] = $res['id']; }
            }
            if (!empty($fileIds)) {
                $payload['resources']['file_search']['file_ids'] = $fileIds;
            }
        }
        return $payload;
    }

    // EmbeddingsProviderContract
    public function embeddings(array $inputs, array $options = []): array
    {
        $payload = [
            'model' => $options['model'] ?? 'text-embedding-3-small',
            'input' => $inputs,
        ];
        if (isset($options['dimensions'])) { $payload['dimensions'] = $options['dimensions']; }
        if (isset($options['encoding_format'])) { $payload['encoding_format'] = $options['encoding_format']; }
        $pending = $this->withOrgProjectHeaders($this->client(), $options);
        $res = $pending->post($this->embeddingsEndpoint, $payload)->json();
        return [
            'embeddings' => array_map(fn($d) => $d['embedding'] ?? [], $res['data'] ?? []),
            'usage' => $res['usage'] ?? [],
            'raw' => $res,
        ];
    }

    // ImageProviderContract
    public function generateImage(string $prompt, array $options = []): array
    {
        $mode = $options['mode'] ?? 'generation';
        $pending = $this->withOrgProjectHeaders($this->client(), $options);
        if ($mode === 'edit') {
            // images/edits requires multipart with image and optional mask
            $req = $pending;
            if (!empty($options['image'])) { $req = $req->attach('image', fopen($options['image'], 'r'), basename($options['image'])); }
            if (!empty($options['mask'])) { $req = $req->attach('mask', fopen($options['mask'], 'r'), basename($options['mask'])); }
            $res = $req->post($this->imageEditsEndpoint, [
                'prompt' => $prompt,
                'model' => $options['model'] ?? 'dall-e-2',
                'size' => $options['size'] ?? '1024x1024',
                'n' => $options['n'] ?? 1,
                'response_format' => $options['response_format'] ?? 'url',
            ])->json();
        } elseif ($mode === 'variation') {
            $req = $pending;
            if (!empty($options['image'])) { $req = $req->attach('image', fopen($options['image'], 'r'), basename($options['image'])); }
            $res = $req->post($this->imageVariationsEndpoint, [
                'model' => $options['model'] ?? 'dall-e-2',
                'size' => $options['size'] ?? '1024x1024',
                'n' => $options['n'] ?? 1,
                'response_format' => $options['response_format'] ?? 'url',
            ])->json();
        } else {
            // default generations
            $payload = [
                'prompt' => $prompt,
                'model' => $options['model'] ?? 'dall-e-3',
                'size' => $options['size'] ?? '1024x1024',
                'n' => $options['n'] ?? 1,
            ];
            if (($payload['model'] ?? '') === 'gpt-image-1') {
                if (isset($options['image_format'])) { $payload['image_format'] = $options['image_format']; }
                if (isset($options['quality'])) { $payload['quality'] = $options['quality']; }
                if (isset($options['moderation'])) { $payload['moderation'] = $options['moderation']; }
            } else {
                $payload['response_format'] = $options['response_format'] ?? 'url';
            }
            $res = $pending->post($this->imageEndpoint, $payload)->json();
        }
        return [
            'images' => $res['data'] ?? [],
            'raw' => $res,
        ];
    }

    // AudioProviderContract
    public function textToSpeech(string $text, array $options = []): array
    {
        $format = $options['format'] ?? 'mp3';
        $payload = [
            'model' => $options['model'] ?? 'tts-1',
            'input' => $text,
            'voice' => $options['voice'] ?? 'alloy',
            'format' => $format,
        ];
        if (isset($options['speed'])) { $payload['speed'] = $options['speed']; }
        if (isset($options['voice_instructions'])) { $payload['voice_instructions'] = $options['voice_instructions']; }
        $pending = $this->withOrgProjectHeaders($this->client(), $options);
        // SSE accumulation if requested
        if (($options['stream'] ?? null) === 'sse') {
            $payload['stream'] = true;
            $response = $pending->withOptions(['stream' => true])->post($this->textToSpeechEndpoint, $payload);
            if (!method_exists($response, 'toPsrResponse')) { return [ 'audio' => '', 'mime' => 'application/octet-stream' ]; }
            $body = $response->toPsrResponse()->getBody();
            $bytes = '';
            while (!$body->eof()) {
                $data = $body->read(2048);
                if ($data === '') { continue; }
                foreach (preg_split('/\r?\n/', $data) as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, ':') || !str_starts_with($line, 'data:')) { continue; }
                    $json = trim(substr($line, 5));
                    if ($json === '[DONE]') { break 2; }
                    $decoded = json_decode($json, true);
                    // Heuristic: look for base64 audio delta under keys 'delta' or nested
                    $delta = $decoded['delta'] ?? ($decoded['audio'] ?? null);
                    if (is_string($delta)) { $bytes .= base64_decode($delta); }
                }
            }
            $b64 = base64_encode($bytes);
        } else {
            $res = $pending->post($this->textToSpeechEndpoint, $payload);
            $b64 = base64_encode($res->body());
        }
        $mime = match ($format) {
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'flac' => 'audio/flac',
            'aac' => 'audio/aac',
            'opus' => 'audio/opus',
            'pcm' => 'audio/pcm',
            default => 'application/octet-stream',
        };
        return [ 'audio' => $b64, 'mime' => $mime ];
    }

    public function speechToText(string $filePath, array $options = []): array
    {
        $payload = [
            'model' => $options['model'] ?? 'whisper-1',
            'response_format' => $options['response_format'] ?? 'json',
        ];
        if (isset($options['language'])) { $payload['language'] = $options['language']; }
        if (isset($options['prompt'])) { $payload['prompt'] = $options['prompt']; }
        if (isset($options['translate'])) { $payload['translate'] = $options['translate']; }
        if (isset($options['temperature'])) { $payload['temperature'] = $options['temperature']; }
        if (isset($options['logprobs'])) { $payload['logprobs'] = $options['logprobs']; }
        $pending = $this->withOrgProjectHeaders($this->client(), $options);
        $endpoint = (!empty($options['translate']) || (($options['mode'] ?? null) === 'translation'))
            ? $this->speechTranslationsEndpoint
            : $this->speechToTextEndpoint;
        $res = $pending->attach('file', fopen($filePath, 'r'), basename($filePath))
            ->post($endpoint, $payload)->json();
        return [ 'text' => $res['text'] ?? '', 'raw' => $res ];
    }

    // ModelsProviderContract
    public function listModels(): array
    {
        $res = $this->withOrgProjectHeaders($this->client(), [])->get($this->modelsEndpoint)->json();
        return $res['data'] ?? [];
    }

    public function getModel(string $id): array
    {
        $res = $this->withOrgProjectHeaders($this->client(), [])->get($this->modelsEndpoint . '/' . urlencode($id))->json();
        return $res ?? [];
    }
}
