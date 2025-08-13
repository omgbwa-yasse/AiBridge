<?php

namespace AiBridge\Facades;

use Illuminate\Support\Facades\Facade;
use AiBridge\Support\ImageNormalizer;
use AiBridge\Support\AudioNormalizer;
use AiBridge\Support\EmbeddingsNormalizer;

class AiBridge extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'AiBridge';
    }

    // Optional convenience helpers (static) for normalization
    public static function normalizeImages(array $raw): array
    {
        return ImageNormalizer::normalize($raw);
    }

    public static function normalizeTTSAudio(array $raw): array
    {
        return AudioNormalizer::normalizeTTS($raw);
    }

    public static function normalizeSTTAudio(array $raw): array
    {
        return AudioNormalizer::normalizeSTT($raw);
    }

    public static function normalizeEmbeddings(array $raw): array
    {
        return EmbeddingsNormalizer::normalize($raw);
    }
}
