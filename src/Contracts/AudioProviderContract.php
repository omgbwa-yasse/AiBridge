<?php

namespace AiBridge\Contracts;

interface AudioProviderContract
{
    /**
     * Text to speech; returns array with audio => base64, mime => string.
     */
    public function textToSpeech(string $text, array $options = []): array;

    /**
     * Speech to text; returns array with text => string, raw? => array.
     */
    public function speechToText(string $filePath, array $options = []): array;
}
