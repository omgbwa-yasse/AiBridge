<?php

namespace AiBridge\Support;

class StreamChunk
{
    public string $text;
    public ?array $usage;
    public ?string $finishReason; // e.g., 'stop', 'length', etc.
    public string $chunkType; // 'delta' | 'end' | 'tool_call' | 'tool_result'
    public array $toolCalls;
    public array $toolResults;

    public function __construct(
        string $text = '',
        ?array $usage = null,
        ?string $finishReason = null,
        string $chunkType = 'delta',
        array $toolCalls = [],
        array $toolResults = []
    ) {
        $this->text = $text;
        $this->usage = $usage;
        $this->finishReason = $finishReason;
        $this->chunkType = $chunkType;
        $this->toolCalls = $toolCalls;
        $this->toolResults = $toolResults;
    }

    public static function delta(string $text): self { return new self($text, null, null, 'delta'); }
    public static function end(?string $finishReason = 'stop', ?array $usage = null): self { return new self('', $usage, $finishReason, 'end'); }
}
