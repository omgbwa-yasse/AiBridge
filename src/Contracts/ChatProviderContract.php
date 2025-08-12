<?php

namespace AiBridge\Contracts;

interface ChatProviderContract
{
    /**
     * Send a chat message and return raw provider response array.
     */
    public function chat(array $messages, array $options = []): array;

    /**
     * Stream a chat completion (generator yielding chunks of text or arrays).
     * @return \Generator<string|array>
     */
    public function stream(array $messages, array $options = []): \Generator;

    /**
     * Whether this provider supports streaming.
     */
    public function supportsStreaming(): bool;
}
