<?php

namespace AiBridge\Providers;

use Illuminate\Support\Facades\Http;
use AiBridge\Contracts\ChatProviderContract;
use AiBridge\Contracts\EmbeddingsProviderContract;

class GeminiProvider implements ChatProviderContract, EmbeddingsProviderContract
{
    protected string $apiKey;
    protected string $chatEndpoint;
    protected string $embedEndpoint = 'https://generativelanguage.googleapis.com/v1beta/models/text-embedding-004:embedContent';

    public function __construct(string $apiKey, string $chatEndpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent')
    {
        $this->apiKey = $apiKey;
        $this->chatEndpoint = $chatEndpoint;
    }

    protected function keyQuery(): string
    {
        return '?key='.$this->apiKey;
    }

    protected function decorate($pending)
    {
        if (function_exists('app')) {
            try {
                $manager = \call_user_func('app', 'AiBridge');
                if (method_exists($manager, 'decorateHttp')) {
                    return $manager->decorateHttp($pending);
                }
            } catch (\Throwable $e) {
                // Intentionally ignore: decoration is optional
            }
        }
        return $pending;
    }

    public function chat(array $messages, array $options = []): array
    {
        // Gemini expects a single content; we join messages user parts.
        $userTexts = array_map(fn($m) => $m['content'], array_filter($messages, fn($m) => ($m['role'] ?? '') !== 'system'));
        $payload = [
            'contents' => [
                [ 'parts' => [ ['text' => implode("\n", $userTexts)] ] ]
            ],
        ];
    $res = $this->decorate(Http::asJson())->post($this->chatEndpoint.$this->keyQuery(), $payload)->json();
        return $res ?? [];
    }

    public function stream(array $messages, array $options = []): \Generator
    {
        // Gemini streaming endpoint variant (simplified stub: fallback non-stream)
        $full = $this->chat($messages, $options);
        $text = $full['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $chunks = str_split($text, 80);
        foreach ($chunks as $c) { yield $c; }
    }

    public function supportsStreaming(): bool
    {
        return true; // Simulated chunking
    }

    public function embeddings(array $inputs, array $options = []): array
    {
        $vectors = [];
        foreach ($inputs as $input) {
            $payload = [
                'model' => 'text-embedding-004',
                'content' => [ 'parts' => [ ['text' => $input] ] ],
            ];
            $res = $this->decorate(Http::asJson())->post($this->embedEndpoint.$this->keyQuery(), $payload)->json();
            $vectors[] = $res['embedding']['values'] ?? [];
        }
        return [ 'embeddings' => $vectors ];
    }
}
