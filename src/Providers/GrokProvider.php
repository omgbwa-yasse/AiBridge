<?php

namespace AiBridge\Providers;

use Illuminate\Support\Facades\Http;
use AiBridge\Contracts\ChatProviderContract;

class GrokProvider implements ChatProviderContract
{
    protected string $apiKey;
    protected string $endpoint;

    public function __construct(string $apiKey, string $endpoint = 'https://api.grok.com/v1/chat')
    {
        $this->apiKey = $apiKey;
        $this->endpoint = $endpoint;
    }

    protected function client()
    {
        $pending = Http::withToken($this->apiKey);
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
        $joined = implode("\n", array_map(fn($m) => $m['content'], $messages));
        $payload = [
            'prompt' => $joined,
            'model' => $options['model'] ?? 'grok-default',
        ];
        $res = $this->client()->post($this->endpoint, $payload)->json();
        return $res ?? [];
    }

    public function stream(array $messages, array $options = []): \Generator
    {
        $full = $this->chat($messages, $options);
        $text = $full['response'] ?? '';
        foreach (str_split($text, 80) as $c) { yield $c; }
    }

    public function supportsStreaming(): bool
    {
        return true;
    }
}
