<?php

namespace AiBridge\Support;

class Document
{
    public string $kind; // local|base64|raw|text|url|chunks|file_id
    public ?string $path = null;
    public ?string $mime = null;
    public ?string $title = null;
    public ?string $base64 = null;
    public ?string $raw = null;
    public ?string $text = null;
    public ?string $url = null;
    /** @var string[] */
    public array $chunks = [];
    public ?string $fileId = null;

    private function __construct(string $kind)
    {
        $this->kind = $kind;
    }

    public static function fromLocalPath(string $path, ?string $title = null, ?string $mime = null): self
    {
        $d = new self('local');
        $d->path = $path;
        $d->title = $title;
        $d->mime = $mime;
        return $d;
    }

    public static function fromBase64(string $base64, string $mime, ?string $title = null): self
    {
        $d = new self('base64');
        $d->base64 = $base64;
        $d->mime = $mime;
        $d->title = $title;
        return $d;
    }

    public static function fromRawContent(string $raw, string $mime, ?string $title = null): self
    {
        $d = new self('raw');
        $d->raw = $raw;
        $d->mime = $mime;
        $d->title = $title;
        return $d;
    }

    public static function fromText(string $text, ?string $title = null): self
    {
        $d = new self('text');
        $d->text = $text;
        $d->mime = 'text/plain';
        $d->title = $title;
        return $d;
    }

    public static function fromUrl(string $url, ?string $title = null): self
    {
        $d = new self('url');
        $d->url = $url;
        $d->title = $title;
        return $d;
    }

    public static function fromChunks(array $chunks, ?string $title = null): self
    {
        $d = new self('chunks');
        $d->chunks = array_values(array_map('strval', $chunks));
        $d->title = $title;
        return $d;
    }

    public static function fromFileId(string $fileId, ?string $title = null): self
    {
        $d = new self('file_id');
        $d->fileId = $fileId;
        $d->title = $title;
        return $d;
    }
}
