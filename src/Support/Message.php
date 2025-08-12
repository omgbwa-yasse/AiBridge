<?php

namespace AiBridge\Support;

class Message
{
    public string $role;
    public string $content;
    public array $attachments = [];

    public function __construct(string $role, string $content, array $attachments = [])
    {
        $this->role = $role;
        $this->content = $content;
        $this->attachments = $attachments;
    }

    public static function user(string $content, array $attachments = []): self
    {
        return new self('user', $content, $attachments);
    }

    public static function system(string $content): self
    {
        return new self('system', $content);
    }

    public static function assistant(string $content): self
    {
        return new self('assistant', $content);
    }

    public function toArray(): array
    {
        return [
            'role' => $this->role,
            'content' => $this->content,
            'attachments' => $this->attachments,
        ];
    }
}
