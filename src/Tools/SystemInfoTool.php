<?php

namespace AiBridge\Tools;

use AiBridge\Contracts\ToolContract;

class SystemInfoTool implements ToolContract
{
    public function name(): string { return 'system_info'; }
    public function description(): string { return 'Retourne des infos système simulées (php_version)'; }
    public function schema(): array { return ['type' => 'object','properties'=>[],'required'=>[]]; }
    public function execute(array $arguments): string { return json_encode(['php_version' => PHP_VERSION]); }
}
