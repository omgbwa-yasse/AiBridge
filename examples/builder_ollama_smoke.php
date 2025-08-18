<?php
require_once __DIR__.'/../vendor/autoload.php';

use AiBridge\AiBridgeManager;

$endpoint = $argv[1] ?? 'http://localhost:11434';
$model = $argv[2] ?? 'gemma3:4b';

$ai = new AiBridgeManager([]);

function out($k,$v){ echo "[$k] ".$v.PHP_EOL; }

try {
    $res = $ai->text()
        ->using('ollama', $model, ['endpoint' => $endpoint])
        ->withPrompt('Dis un mot positif.')
        ->withMaxTokens(32)
        ->usingTemperature(0.2)
        ->asText();

    out('Text', substr($res['text'] ?? '', 0, 160));

    $buf = '';
    foreach ($ai->text()->using('ollama', $model, ['endpoint' => $endpoint])->withPrompt("Explique en une phrase ce qu'est une étoile filante.")->asStream() as $chunk) {
        $buf .= is_string($chunk) ? $chunk : json_encode($chunk);
        if (strlen($buf) >= 160) { break; }
    }
    out('StreamSample', substr($buf, 0, 160));
    out('Status', 'Done');
} catch (Throwable $e) {
    out('Error', $e->getMessage());
    exit(1);
}
