<?php

namespace AiBridge\Contracts;

interface ModelsProviderContract
{
    /** List models metadata as returned by the provider */
    public function listModels(): array;

    /** Retrieve a single model metadata by id/name */
    public function getModel(string $id): array;
}
