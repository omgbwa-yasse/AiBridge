<?php

namespace AiBridge\Providers;

/**
 * MistralProvider
 *
 * Targets Mistral AI API (https://api.mistral.ai) with OpenAI-compatible endpoints.
 * Mistral uses the same API schema as OpenAI for chat completions and embeddings.
 */
class MistralProvider extends OpenAIProvider
{
    /**
     * @param string $apiKey Mistral API key
     * @param string $chatEndpoint Base URL, defaults to https://api.mistral.ai/v1/chat/completions
     */
    public function __construct(string $apiKey, string $chatEndpoint = 'https://api.mistral.ai/v1/chat/completions')
    {
        parent::__construct($apiKey, $chatEndpoint);
        
        // Override endpoints for Mistral
        $this->modelsEndpoint = 'https://api.mistral.ai/v1/models';
        $this->embeddingsEndpoint = 'https://api.mistral.ai/v1/embeddings';
        $this->chatEndpoint = $chatEndpoint;
    }
}
