<?php
// Simple smoke test for Ollama provider.
// Usage: php examples/ollama_smoke.php [model] [endpoint]
// Defaults: model=gemma3:4b endpoint=http://localhost:11434

require_once __DIR__.'/../vendor/autoload.php';

use AiBridge\AiBridgeManager;

$model = $argv[1] ?? 'gemma3:4b';
$endpoint = $argv[2] ?? 'http://localhost:11434';

$manager = new AiBridgeManager([
  'ollama' => ['endpoint' => $endpoint]
]);

function show($k,$v){ echo "[$k] $v".PHP_EOL; }

// Basic chat
$resp = $manager->chat('ollama', [ ['role'=>'user','content'=>'Donne un mot pour décrire le ciel']] , ['model'=>$model]);
show('Chat', $resp['message']['content'] ?? json_encode($resp));

// Streaming sample (first 150 chars)
$streamOut = '';
foreach ($manager->stream('ollama', [ ['role'=>'user','content'=>'Explique la photosynthèse en quelques mots.'] ], ['model'=>$model]) as $chunk) {
    $streamOut .= $chunk;
    if (strlen($streamOut) > 150) { break; }
}
show('StreamSample', substr($streamOut,0,150));

// JSON format
$json = $manager->chat('ollama', [ ['role'=>'user','content'=>'Donne JSON {"etat":"OK"} uniquement.'] ], ['model'=>$model,'response_format'=>'json']);
show('JSON', $json['message']['content'] ?? '');

show('Status','Done');
