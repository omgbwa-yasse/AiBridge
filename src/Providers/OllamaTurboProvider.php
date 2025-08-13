<?php

namespace AiBridge\Providers;

/**
 * OllamaTurboProvider
 *
 * Targets Ollama Turbo (SaaS) at https://ollama.com with API key auth.
 * Uses the same API surface as OllamaProvider (/api/chat, /api/generate, /api/embeddings),
 * adding the Authorization header. Compatible with existing Chat/Stream/Embeddings methods.
 */
class OllamaTurboProvider extends OllamaProvider
{
    protected ?string $apiKey;

    /**
     * @param string|null $apiKey API key for https://ollama.com (will be sent as Bearer token)
     * @param string $endpoint Base URL, defaults to https://ollama.com
     */
    public function __construct(?string $apiKey, string $endpoint = 'https://ollama.com')
    {
        parent::__construct($endpoint);
        $this->apiKey = $apiKey;
    }

    protected function decorate($pending)
    {
        $pending = parent::decorate($pending);
        if ($this->apiKey) {
            $value = stripos($this->apiKey, 'bearer ') === 0 ? $this->apiKey : ('Bearer ' . $this->apiKey);
            return $pending->withHeaders(['Authorization' => $value]);
        }
        return $pending;
    }
}
