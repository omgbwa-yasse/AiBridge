<?php
require_once __DIR__.'/../vendor/autoload.php';

use AiBridge\AiBridgeManager;

$endpoint = $argv[1] ?? 'http://localhost:11434';
$model = $argv[2] ?? 'gemma3:4b';

$ai = new AiBridgeManager([]);

$g = $ai->text()->using('ollama', $model, ['endpoint' => $endpoint])->withPrompt('Dis bonjour.')->asStream();

$shown = 0;
foreach ($g as $chunk) {
    if (is_object($chunk) && property_exists($chunk, 'text')) {
        echo $chunk->text;
    } elseif (is_string($chunk)) {
        echo $chunk;
    } else {
        echo json_encode($chunk);
    }
    $shown++;
    if ($shown >= 5) { break; }
}
