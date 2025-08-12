<?php

namespace AiBridge\Support\Exceptions;

use RuntimeException;

class ProviderException extends RuntimeException
{
    public static function notFound(string $name): self
    {
        return new self("Provider '$name' introuvable");
    }

    public static function unsupported(string $name, string $feature): self
    {
        return new self("Provider '$name' ne supporte pas $feature");
    }
}
