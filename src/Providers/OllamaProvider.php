<?php

namespace AiBridge\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Facade;
use Illuminate\Http\Client\Factory as HttpFactory;
use AiBridge\Contracts\ChatProviderContract;
use AiBridge\Contracts\EmbeddingsProviderContract;
use AiBridge\Contracts\ImageProviderContract;
use AiBridge\Contracts\AudioProviderContract;
use AiBridge\Support\Exceptions\ProviderException;
use AiBridge\Support\FileSecurity;
use AiBridge\Support\DocumentAttachmentMapper;

/**
 * OllamaProvider
 * Implements chat, streaming, embeddings, basic image generation (for compatible models like stable-diffusion),
 * simple structured JSON output support (format => json) and rudimentary multi‑modal (vision) input by attaching
 * base64 encoded images to the last user message when image files are supplied.
 */
class OllamaProvider implements ChatProviderContract, EmbeddingsProviderContract, ImageProviderContract, AudioProviderContract
{
    protected string $base;
    protected string $chatEndpoint;
    protected string $embeddingsEndpoint;
    protected string $generateEndpoint;

    public function __construct(string $endpoint = 'http://localhost:11434')
    {
        $this->base = rtrim($endpoint, '/');
        $this->chatEndpoint = $this->base.'/api/chat';
    $this->embeddingsEndpoint = $this->base.'/api/embeddings';
    $this->generateEndpoint = $this->base.'/api/generate';
    }

    protected function normalizeMessages(array $messages): array
    {
        // Ollama expects an array of messages with role/content already.
        return $messages;
    }

    protected function makePending(array $options = [])
    {
        // Prefer the Http facade when available so fakes/swaps are honored in tests or apps
        if (class_exists(Http::class)) {
            try {
                return Http::withOptions($options);
            } catch (\Throwable $e) {
                // fall through to factory when facade isn't usable
            }
        }
        static $factory = null;
        if (!$factory) { $factory = new HttpFactory(); }
        return $factory->withOptions($options);
    }

    public function chat(array $messages, array $options = []): array
    {
        // Map attachments embedded in messages to Ollama-compatible options
        $accFiles = [];
        $accImages = [];
        foreach ($messages as $i => $m) {
            $atts = $m['attachments'] ?? [];
            if (!empty($atts)) {
                $mapped = DocumentAttachmentMapper::toOllamaOptions($atts);
                // Inline text chunks appended to message content
                if (!empty($mapped['inlineTexts'])) {
                    $messages[$i]['content'] = rtrim(($m['content'] ?? '')) . "\n" . implode("\n\n", $mapped['inlineTexts']);
                }
                $accFiles = array_merge($accFiles, $mapped['files']);
                $accImages = array_merge($accImages, $mapped['image_files']);
                // Drop attachments field to avoid leaking provider-agnostic structure
                unset($messages[$i]['attachments']);
            }
        }
        $payload = [
            'model' => $options['model'] ?? 'llama2',
            'messages' => $this->normalizeMessages($messages),
            'stream' => false,
        ];
    if (!empty($options['temperature'])) { $payload['options']['temperature'] = $options['temperature']; }
    if (!empty($options['top_p'])) { $payload['options']['top_p'] = $options['top_p']; }
    if (!empty($options['top_k'])) { $payload['options']['top_k'] = $options['top_k']; }
    if (!empty($options['repeat_penalty'])) { $payload['options']['repeat_penalty'] = $options['repeat_penalty']; }
    if (!empty($options['stop'])) { $payload['options']['stop'] = (array)$options['stop']; }

        // Structured JSON output request (Ollama supports format => json)
        if (($options['response_format'] ?? null) === 'json') {
            $payload['format'] = 'json';
            $hasSystem = false;
            foreach ($payload['messages'] as $m) {
                if (($m['role'] ?? null) === 'system') { $hasSystem = true; break; }
            }
            if (!$hasSystem) {
                array_unshift($payload['messages'], [
                    'role' => 'system',
                    'content' => 'Tu dois répondre uniquement en JSON valide sans texte additionnel.'
                ]);
            }
        }

        // File attachments (non-image generic) + multimodal images
        // Merge mapped files/images with any provided via options
        $optFiles = !empty($options['files']) && is_array($options['files']) ? $this->prepareFiles($options['files']) : [];
        $optImages = !empty($options['image_files']) && is_array($options['image_files']) ? $this->prepareImageFiles($options['image_files']) : [];
        $files = array_merge($optFiles, $accFiles);
        $images = array_merge($optImages, $accImages);
        if (!empty($files)) {
            $payload['files'] = $files;
        }
        if (!empty($images)) {
            if (!empty($images)) {
                // Attach images to last user message or create new user message
                $lastIndex = count($payload['messages']) - 1;
                if ($lastIndex >= 0 && ($payload['messages'][$lastIndex]['role'] ?? null) === 'user') {
                    $payload['messages'][$lastIndex]['images'] = $images;
                } else {
                    $payload['messages'][] = [ 'role' => 'user', 'content' => '', 'images' => $images ];
                }
            }
        }

    $pending = $this->makePending()->asJson();
    $res = $this->decorate($pending)->post($this->chatEndpoint, $payload)->json();
        return $res ?? [];
    }

    public function stream(array $messages, array $options = []): \Generator
    {
        // Map attachments embedded in messages similar to chat()
        $accFiles = [];
        $accImages = [];
        foreach ($messages as $i => $m) {
            $atts = $m['attachments'] ?? [];
            if (!empty($atts)) {
                $mapped = DocumentAttachmentMapper::toOllamaOptions($atts);
                if (!empty($mapped['inlineTexts'])) {
                    $messages[$i]['content'] = rtrim(($m['content'] ?? '')) . "\n" . implode("\n\n", $mapped['inlineTexts']);
                }
                $accFiles = array_merge($accFiles, $mapped['files']);
                $accImages = array_merge($accImages, $mapped['image_files']);
                unset($messages[$i]['attachments']);
            }
        }
    $payload = [ 'model' => $options['model'] ?? 'llama2', 'messages' => $this->normalizeMessages($messages), 'stream' => true ];
    if (!empty($options['temperature'])) { $payload['options']['temperature'] = $options['temperature']; }
    if (!empty($options['top_p'])) { $payload['options']['top_p'] = $options['top_p']; }
    if (!empty($options['top_k'])) { $payload['options']['top_k'] = $options['top_k']; }
    if (!empty($options['repeat_penalty'])) { $payload['options']['repeat_penalty'] = $options['repeat_penalty']; }
    if (!empty($options['stop'])) { $payload['options']['stop'] = (array)$options['stop']; }
        if (($options['response_format'] ?? null) === 'json') {
            $payload['format'] = 'json';
        }
    $optFiles = !empty($options['files']) && is_array($options['files']) ? $this->prepareFiles($options['files']) : [];
    $optImages = !empty($options['image_files']) && is_array($options['image_files']) ? $this->prepareImageFiles($options['image_files']) : [];
    $files = array_merge($optFiles, $accFiles);
    $images = array_merge($optImages, $accImages);
    if (!empty($files)) { $payload['files'] = $files; }
    if (!empty($images)) {
                $lastIndex = count($payload['messages']) - 1;
                if ($lastIndex >= 0 && ($payload['messages'][$lastIndex]['role'] ?? null) === 'user') {
                    $payload['messages'][$lastIndex]['images'] = $images;
                } else {
                    $payload['messages'][] = [ 'role' => 'user', 'content' => '', 'images' => $images ];
                }
        }
    $response = $this->decorate($this->makePending(['stream' => true]))->post($this->chatEndpoint, $payload);
        if (method_exists($response, 'toPsrResponse')) {
            $body = $response->toPsrResponse()->getBody();
            while (!$body->eof()) {
                $chunk = $body->read(4096);
                if (!$chunk) { continue; }
                $lines = preg_split('/\r?\n/', $chunk);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') { continue; }
                    $decoded = json_decode($line, true);
                    if (isset($decoded['message']['content'])) {
                        yield $decoded['message']['content'];
                    }
                }
            }
        }
    }

    public function supportsStreaming(): bool
    {
        return true;
    }

    public function embeddings(array $inputs, array $options = []): array
    {
        $model = $options['model'] ?? 'nomic-embed-text';
        $vectors = [];
        foreach ($inputs as $text) {
            $res = $this->decorate($this->makePending()->asJson())->post($this->embeddingsEndpoint, [
                'model' => $model,
                'prompt' => $text,
            ])->json();
            $vectors[] = $res['embedding'] ?? [];
        }
        return [ 'embeddings' => $vectors, 'raw' => null ];
    }

    protected function prepareFiles(array $files): array
    {
        $out = [];
        $fs = FileSecurity::fromConfig();
        foreach ($files as $file) {
            if (!is_string($file) || !file_exists($file)) { continue; }
            if (!$fs->validateFile($file, false)) { continue; }
            $out[] = [
                'name' => basename($file),
                'type' => mime_content_type($file) ?: 'application/octet-stream',
                'content' => base64_encode(file_get_contents($file)),
            ];
        }
        return $out;
    }

    protected function prepareImageFiles(array $files): array
    {
        $images = [];
        $fs = FileSecurity::fromConfig();
        foreach ($files as $file) {
            if (!is_string($file) || !file_exists($file)) { continue; }
            $mime = mime_content_type($file) ?: '';
            if (str_starts_with($mime, 'image/') && $fs->validateFile($file, true)) {
                $images[] = base64_encode(file_get_contents($file));
            }
        }
        return $images;
    }

    /**
     * Basic image generation using a diffusion / image model loaded in Ollama (e.g. stable-diffusion).
     * Returns base64 images if present, else raw textual response.
     */
    public function generateImage(string $prompt, array $options = []): array
    {
        $payload = [
            'model' => $options['model'] ?? 'stable-diffusion',
            'prompt' => $prompt,
            'stream' => false,
        ];
        if (!empty($options['negative_prompt'])) {
            $payload['negative'] = $options['negative_prompt'];
        }
    $res = $this->decorate($this->makePending()->asJson())->post($this->generateEndpoint, $payload)->json();
        $images = [];
        // Heuristic: some builds may return 'images' => [b64,...] or 'image' single.
        if (isset($res['images']) && is_array($res['images'])) {
            foreach ($res['images'] as $img) { $images[] = ['b64' => $img]; }
        } elseif (isset($res['image'])) {
            $images[] = ['b64' => $res['image']];
        } elseif (isset($res['response']) && str_starts_with($res['response'], 'data:image')) {
            // Extract base64 part if a data URL
            if (preg_match('/base64,(.*)$/', $res['response'], $m)) {
                $images[] = ['b64' => $m[1]];
            }
        }
        return [
            'images' => $images,
            'meta' => [ 'model' => $payload['model'] ],
            'raw' => $res,
        ];
    }

    // Audio interfaces are not natively supported by Ollama at this time.
    public function textToSpeech(string $text, array $options = []): array
    {
        throw ProviderException::unsupported('ollama', 'tts');
    }

    public function speechToText(string $filePath, array $options = []): array
    {
        throw ProviderException::unsupported('ollama', 'stt');
    }

    protected function decorate($pending)
    {
        if (function_exists('app')) {
            try {
                $manager = \call_user_func('app', 'AiBridge');
                if (is_object($manager) && method_exists($manager, 'decorateHttp')) {
                    return $manager->decorateHttp($pending);
                }
            } catch (\Throwable $e) { /* ignore */ }
        }
        return $pending;
    }
}
