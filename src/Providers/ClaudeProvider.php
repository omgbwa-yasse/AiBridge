<?php

namespace AiBridge\Providers;

use Illuminate\Support\Facades\Http;
use AiBridge\Contracts\ChatProviderContract;

class ClaudeProvider implements ChatProviderContract
{
    protected string $apiKey;
    protected string $endpoint;

    public function __construct(string $apiKey, string $endpoint = 'https://api.anthropic.com/v1/messages')
    {
        $this->apiKey = $apiKey;
        $this->endpoint = $endpoint;
    }

    protected function client()
    {
        $pending = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ]);
        return $this->decorate($pending);
    }

    protected function decorate($pending)
    {
        if (function_exists('app')) {
            try {
                $m = \call_user_func('app', 'AiBridge');
                if (method_exists($m, 'decorateHttp')) {
                    return $m->decorateHttp($pending);
                }
            } catch (\Throwable $e) {
                // Intentionally ignore: decoration is optional
            }
        }
        return $pending;
    }

    public function chat(array $messages, array $options = []): array
    {
        // Anthropic expects messages without explicit system (converted to user)
        $converted = array_map(function ($m) {
            if (($m['role'] ?? '') === 'system') {
                return ['role' => 'user', 'content' => $m['content']];
            }
            return $m;
        }, $messages);
        $payload = [
            'model' => $options['model'] ?? 'claude-3-opus-20240229',
            'max_tokens' => $options['max_tokens'] ?? 512,
            'messages' => $converted,
        ];
        if (isset($options['temperature'])) { $payload['temperature'] = $options['temperature']; }
        $res = $this->client()->post($this->endpoint, $payload)->json();
        return $res ?? [];
    }

    public function stream(array $messages, array $options = []): \Generator
    {
        // Simplified: non true SSE fallback simulated
        $full = $this->chat($messages, $options);
        $text = '';
        if (!empty($full['content'][0]['text'])) { $text = $full['content'][0]['text']; }
        foreach (str_split($text, 60) as $part) { yield $part; }
    }

    public function supportsStreaming(): bool
    {
        return true; // simulated chunk splitting
    }
}
