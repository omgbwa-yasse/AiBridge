<?php

namespace AiBridge\Support;

class ChatNormalizer
{
    /**
     * Normalize heterogeneous provider responses into a common shape:
     * [ 'text' => string, 'tool_calls' => array, 'raw' => array ]
     */
    public static function normalize(array $raw): array
    {
        $text = '';
        if (isset($raw['choices'][0]['message']['content'])) {
            $text = (string)$raw['choices'][0]['message']['content'];
        } elseif (isset($raw['message']['content'])) {
            $text = (string)$raw['message']['content'];
        } elseif (isset($raw['response'])) {
            $text = (string)$raw['response'];
        }
        $toolCalls = [];
        if (isset($raw['choices'][0]['message']['tool_calls'])) {
            $toolCalls = $raw['choices'][0]['message']['tool_calls'];
        } elseif (isset($raw['tool_calls'])) {
            $toolCalls = $raw['tool_calls'];
        }
        return [ 'text' => $text, 'tool_calls' => $toolCalls, 'raw' => $raw ];
    }
}
