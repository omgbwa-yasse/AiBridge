<?php

namespace AiBridge\Contracts;

interface ToolContract
{
    public function name(): string;
    public function description(): string;
    public function schema(): array; // simple param schema
    public function execute(array $arguments): string; // must return string
}
