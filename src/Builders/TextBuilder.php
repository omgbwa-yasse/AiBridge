<?php

namespace AiBridge\Builders;

use AiBridge\AiBridgeManager;
use AiBridge\Support\ChatNormalizer;
use AiBridge\Support\Document;

/**
 * Fluent builder for text generation over AiBridge providers.
 * Keeps method names short and explicit, reducing array option errors.
 */
class TextBuilder
{
    private const ERR_MISSING_USING = 'Provider and model must be set via using().';
    private AiBridgeManager $manager;
    private ?string $provider = null;
    private ?string $model = null;
    private array $providerConfig = []; // per-call overrides (api_key, endpoint, base_url, ...)
    private array $messages = [];
    private ?string $systemPrompt = null;
    private ?int $maxTokens = null;
    private ?float $temperature = null;
    private ?float $topP = null;

    public function __construct(AiBridgeManager $manager)
    {
        $this->manager = $manager;
    }

    // Select provider and model
    public function using(string $provider, string $model, array $providerConfig = []): self
    {
        $this->provider = $provider;
        $this->model = $model;
        $this->providerConfig = $providerConfig;
        return $this;
    }

    // Single-prompt (user) with optional attachments
    public function withPrompt(string $text, array $attachments = []): self
    {
        $msg = ['role' => 'user', 'content' => $text];
        if (!empty($attachments)) { $msg['attachments'] = $attachments; }
        $this->messages[] = $msg;
        return $this;
    }

    // Aliases for brevity
    public function prompt(string $text): self { return $this->withPrompt($text); }

    // System prompt
    public function withSystemPrompt(string $text): self
    {
        $this->systemPrompt = $text;
        return $this;
    }


    public function withMaxTokens(int $tokens): self { $this->maxTokens = $tokens; return $this; }
    public function usingTemperature(float $t): self { $this->temperature = $t; return $this; }
    public function usingTopP(float $p): self { $this->topP = $p; return $this; }


    private function buildMessages(): array
    {
        $msgs = $this->messages;
        if ($this->systemPrompt) {
            array_unshift($msgs, ['role' => 'system', 'content' => $this->systemPrompt]);
        }
        return $msgs;
    }

    private function callOptions(): array
    {
        $opts = $this->providerConfig;
        if ($this->model) { $opts['model'] = $this->model; }
        if ($this->maxTokens !== null) { $opts['max_tokens'] = $this->maxTokens; }
        if ($this->temperature !== null) { $opts['temperature'] = $this->temperature; }
        if ($this->topP !== null) { $opts['top_p'] = $this->topP; }
        return $opts;
    }

    // Execute and get a normalized text output
    public function asText(): array
    {
        if (!$this->provider || !$this->model) { throw new \InvalidArgumentException(self::ERR_MISSING_USING); }
        $res = $this->manager->chat($this->provider, $this->buildMessages(), $this->callOptions());
        $norm = ChatNormalizer::normalize($res);
        return [
            'text' => $norm['text'] ?? '',
            'raw' => $res,
            'usage' => $norm['usage'] ?? null,
            'finish_reason' => $norm['finish_reason'] ?? null,
        ];
    }

    // Execute and return raw response
    public function asRaw(): array
    {
        if (!$this->provider || !$this->model) { throw new \InvalidArgumentException(self::ERR_MISSING_USING); }
        return $this->manager->chat($this->provider, $this->buildMessages(), $this->callOptions());
    }

    // Streaming: return generator of string chunks
    public function asStream(): \Generator
    {
        if (!$this->provider || !$this->model) { throw new \InvalidArgumentException(self::ERR_MISSING_USING); }
        return $this->manager->stream($this->provider, $this->buildMessages(), $this->callOptions());
    }

    // Attachments helpers (avoid manual arrays)
    // Provider config convenience (avoid passing arrays)
    public function withApiKey(string $key): self { $this->providerConfig['api_key'] = $key; return $this; }
    public function withEndpoint(string $endpoint): self { $this->providerConfig['endpoint'] = $endpoint; return $this; }
    public function withBaseUrl(string $baseUrl): self { $this->providerConfig['base_url'] = $baseUrl; return $this; }
    public function withChatEndpoint(string $url): self { $this->providerConfig['chat_endpoint'] = $url; return $this; }
    public function withAuthHeader(string $header, string $prefix = 'Bearer '): self { $this->providerConfig['auth_header'] = $header; $this->providerConfig['auth_prefix'] = $prefix; return $this; }
    public function withExtraHeaders(array $headers): self { $this->providerConfig['extra_headers'] = $headers; return $this; }
    public function withPaths(array $paths): self { $this->providerConfig['paths'] = $paths; return $this; }
}
