<?php

namespace AiBridge\Contracts;

interface ImageProviderContract
{
    /**
     * Generate images from a prompt; return array with images => [ [ 'url'| 'b64' => ... ] ], meta => [...].
     */
    public function generateImage(string $prompt, array $options = []): array;
}
