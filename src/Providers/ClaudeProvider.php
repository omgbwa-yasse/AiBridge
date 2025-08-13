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
        // Prefer Laravel Http facade when available; otherwise use standalone Factory.
        $headers = [
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ];
        try {
            if (class_exists(\Illuminate\Support\Facades\Facade::class)
                && \Illuminate\Support\Facades\Facade::getFacadeApplication()
                && class_exists(\Illuminate\Support\Facades\Http::class)) {
                $pending = Http::withHeaders($headers)->acceptJson();
                $verifyEnv = getenv('LLM_HTTP_VERIFY');
                if (is_string($verifyEnv) && in_array(strtolower($verifyEnv), ['0','false','off'], true)) {
                    $pending = $pending->withOptions(['verify' => false]);
                }
                return $this->decorate($pending);
            }
        } catch (\Throwable $e) {
            // fall through to factory
        }

        // Fallback: raw HTTP client factory usable outside Laravel
        $factoryClass = \Illuminate\Http\Client\Factory::class;
        if (class_exists($factoryClass)) {
            static $factory; if (!$factory) { $factory = new \Illuminate\Http\Client\Factory(); }
            $pending = $factory->withHeaders($headers)->acceptJson();
            $verifyEnv = getenv('LLM_HTTP_VERIFY');
            if (is_string($verifyEnv) && in_array(strtolower($verifyEnv), ['0','false','off'], true)) {
                $pending = $pending->withOptions(['verify' => false]);
            }
            return $this->decorate($pending);
        }

        // Last resort (should not happen given package requirements)
        return $this->decorate(Http::withHeaders($headers)->acceptJson());
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
