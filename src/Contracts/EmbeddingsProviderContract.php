<?php

namespace AiBridge\Contracts;

interface EmbeddingsProviderContract
{
    /**
     * Generate embeddings for one or multiple inputs.
     * Should return an array with keys: embeddings => [[vector], ...], usage => [...].
     */
    public function embeddings(array $inputs, array $options = []): array;
}
