<?php

namespace AiBridge\Support;

use AiBridge\Contracts\ToolContract;

class ToolRegistry
{
    protected array $tools = [];

    public function register(ToolContract $tool): self
    {
        $this->tools[$tool->name()] = $tool;
        return $this;
    }

    public function get(string $name): ?ToolContract
    {
        return $this->tools[$name] ?? null;
    }

    public function all(): array
    {
        return $this->tools;
    }
}
