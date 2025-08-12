<?php

namespace AiBridge\Support;

class FileSecurity
{
    protected int $maxBytes;
    protected array $allowedFiles;
    protected array $allowedImages;

    public function __construct(array $config)
    {
        $this->maxBytes = $config['max_file_bytes'] ?? (2 * 1024 * 1024);
        $this->allowedFiles = $config['allowed_mime_files'] ?? [];
        $this->allowedImages = $config['allowed_mime_images'] ?? [];
    }

    public function validateFile(string $path, bool $image = false): bool
    {
        $valid = true;
        if (!file_exists($path)) { $valid = false; }
        elseif (filesize($path) > $this->maxBytes) { $valid = false; }
        else {
            $mime = mime_content_type($path) ?: '';
            $allowed = $image ? $this->allowedImages : $this->allowedFiles;
            if (!in_array($mime, $allowed, true)) { $valid = false; }
        }
        return $valid;
    }

    public static function fromConfig(): self
    {
        try {
            if (function_exists('config')) {
                $cfg = config('llm-ai-chat.security', []);
                return new self($cfg);
            }
        } catch (\Throwable $e) {
            // Silent fallback to defaults (outside Laravel context)
        }
        return new self([]);
    }
}
