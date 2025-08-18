<?php
// Smoke test for per-call overrides with Ollama (no provider pre-config in manager)
// Usage: php examples/ollama_override_smoke.php [endpoint] [model]
// Defaults: endpoint=http://localhost:11434 model=auto (first installed)

require_once __DIR__.'/../vendor/autoload.php';

use AiBridge\AiBridgeManager;

$endpoint = $argv[1] ?? 'http://localhost:11434';
$desiredModel = $argv[2] ?? null; // if null, auto-detect

function echoKv($k,$v){ echo "[$k] ".$v.PHP_EOL; }

// Try to detect an installed model if none provided
$model = $desiredModel;
if (!$model) {
    $tagsUrl = rtrim($endpoint,'/').'/api/tags';
    $opts = ['http' => ['method' => 'GET', 'timeout' => 3]];
    $ctx = stream_context_create($opts);
    $json = @file_get_contents($tagsUrl, false, $ctx);
    if ($json !== false) {
        $data = json_decode($json, true);
        $models = $data['models'] ?? [];
        if (!empty($models)) {
            $model = $models[0]['name'] ?? null;
        }
    }
    if (!$model) { $model = 'gemma3:4b'; }
}

echoKv('Endpoint', $endpoint);

echoKv('Model', $model);

$ai = new AiBridgeManager([]); // no provider configured on purpose

try {
    // Chat with per-call endpoint override
    $resp = $ai->chat('ollama', [
        ['role' => 'user', 'content' => 'Dis un mot pour décrire la mer.']
    ], [
        'endpoint' => $endpoint,
        'model' => $model,
    ]);
    echoKv('Chat', $resp['message']['content'] ?? json_encode($resp));

    // Short stream sample
    $buf = '';
    foreach ($ai->stream('ollama', [
        ['role' => 'user', 'content' => "Explique en une phrase ce qu'est une étoile filante."]
    ], [
        'endpoint' => $endpoint,
        'model' => $model,
    ]) as $chunk) {
        $buf .= $chunk;
        if (strlen($buf) >= 150) { break; }
    }
    echoKv('StreamSample', substr($buf,0,150));

    // JSON mode
    $json = $ai->chat('ollama', [
        ['role' => 'user', 'content' => 'Réponds uniquement le JSON {"ok":true}']
    ], [
        'endpoint' => $endpoint,
        'model' => $model,
        'response_format' => 'json',
    ]);
    echoKv('JSON', $json['message']['content'] ?? '');

    echoKv('Status', 'Done');
} catch (Throwable $e) {
    echoKv('Error', $e->getMessage());
    exit(1);
}
