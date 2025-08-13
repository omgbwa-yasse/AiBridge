<?php

namespace AiBridge\Support;

class ImageNormalizer
{
    private const DEFAULT_MIME = 'image/png';

    /**
     * Normalize heterogeneous image responses into a common array of items:
     *   [ { 'type': 'b64', 'mime': 'image/png', 'data': '...' } | { 'type': 'url', 'url': '...' } ]
     * It tries common OpenAI (data/url) and Ollama (b64) shapes.
     */
    public static function normalize(array $raw): array
    {
        $items = self::fromOpenAI($raw);
        if (!empty($items)) { return $items; }
        $items = self::fromOllama($raw);
        if (!empty($items)) { return $items; }
        return self::fromDataUrl($raw);
    }

    private static function fromOpenAI(array $raw): array
    {
        $out = [];
        foreach (($raw['data'] ?? []) as $d) {
            if (!empty($d['url'])) {
                $out[] = [ 'type' => 'url', 'url' => $d['url'] ];
            } elseif (!empty($d['b64_json'])) {
                $out[] = [ 'type' => 'b64', 'mime' => self::DEFAULT_MIME, 'data' => $d['b64_json'] ];
            }
        }
        return $out;
    }

    private static function fromOllama(array $raw): array
    {
        $out = [];
        if (empty($raw['images']) || !is_array($raw['images'])) { return $out; }
        foreach ($raw['images'] as $img) {
            $b64 = $img['b64'] ?? null;
            if (is_string($b64) && $b64 !== '') {
                $out[] = [ 'type' => 'b64', 'mime' => self::DEFAULT_MIME, 'data' => $b64 ];
            }
        }
        return $out;
    }

    private static function fromDataUrl(array $raw): array
    {
        $out = [];
        $resp = $raw['response'] ?? null;
        if (!is_string($resp) || !str_starts_with($resp, 'data:image')) { return $out; }
        if (preg_match('/^data:([^;]+);base64,(.*)$/', $resp, $m)) {
            $out[] = [ 'type' => 'b64', 'mime' => $m[1] ?? self::DEFAULT_MIME, 'data' => $m[2] ?? '' ];
        }
        return $out;
    }
}
