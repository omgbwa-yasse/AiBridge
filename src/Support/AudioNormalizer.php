<?php

namespace AiBridge\Support;

class AudioNormalizer
{
    private const DEFAULT_MIME = 'audio/mpeg';
    /**
     * Normalize Text-to-Speech responses into a simple structure:
     * [ 'b64' => string, 'mime' => string ]
     * Accepts shapes like: [ 'audio' => base64, 'mime' => 'audio/mpeg' ] or raw bytes.
     */
    public static function normalizeTTS(array $rawOrShape): array
    {
        // Common shape used by providers in this package
        if (isset($rawOrShape['audio'])) {
            return [ 'b64' => $rawOrShape['audio'], 'mime' => $rawOrShape['mime'] ?? self::DEFAULT_MIME ];
        }
        // Fallbacks: different keys
        if (isset($rawOrShape['data'])) {
            return [ 'b64' => $rawOrShape['data'], 'mime' => $rawOrShape['mime'] ?? self::DEFAULT_MIME ];
        }
        // Unknown; return empty structure
    return [ 'b64' => '', 'mime' => self::DEFAULT_MIME ];
    }

    /**
     * Normalize Speech-to-Text responses into a simple structure:
     * [ 'text' => string ]
     */
    public static function normalizeSTT(array $raw): array
    {
        if (isset($raw['text'])) { return [ 'text' => (string)$raw['text'] ]; }
        if (isset($raw['transcript'])) { return [ 'text' => (string)$raw['transcript'] ]; }
        return [ 'text' => '' ];
    }
}
