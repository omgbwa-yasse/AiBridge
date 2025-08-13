<?php

namespace AiBridge\Support;

class EmbeddingsNormalizer
{
    /**
     * Normalize embeddings responses into [ 'vectors' => array<array<float>>, 'usage' => array, 'raw' => array|null ]
     */
    public static function normalize(array $raw): array
    {
        $vectors = [];
        $usage = [];
        if (!empty($raw['data']) && is_array($raw['data'])) {
            $vectors = array_map(fn($d) => $d['embedding'] ?? [], $raw['data']);
            $usage = $raw['usage'] ?? [];
        } elseif (!empty($raw['embeddings']) && is_array($raw['embeddings'])) {
            $vectors = $raw['embeddings'];
        }
        return [ 'vectors' => $vectors, 'usage' => $usage, 'raw' => $raw ];
    }
}
