<?php
// Simple smoke test for Ollama Turbo provider (https://ollama.com).
// Usage:
//   set OLLAMA_TURBO_API_KEY in your environment, then:
//     php examples/ollama_turbo_smoke.php [model] [endpoint]
// Defaults: model=gpt-oss:20b endpoint=https://ollama.com

require_once __DIR__.'/../vendor/autoload.php';

use AiBridge\AiBridgeManager;

$model = $argv[1] ?? 'gpt-oss:20b';
$endpoint = $argv[2] ?? 'https://ollama.com';
$apiKey = getenv('OLLAMA_TURBO_API_KEY') ?: ($argv[3] ?? null);

$manager = new AiBridgeManager([
  'ollama_turbo' => [
    'endpoint' => $endpoint,
    'api_key' => $apiKey,
  ],
]);

function show($k,$v){ echo "[$k] $v".PHP_EOL; }

// Basic chat
$resp = $manager->chat('ollama_turbo', [ ['role'=>'user','content'=>'Give one word to describe the sky']] , ['model'=>$model]);
show('Chat', $resp['message']['content'] ?? json_encode($resp));

// Streaming sample (first 150 chars)
$streamOut = '';
foreach ($manager->stream('ollama_turbo', [ ['role'=>'user','content'=>'Explain photosynthesis briefly.'] ], ['model'=>$model]) as $chunk) {
    $streamOut .= $chunk;
    if (strlen($streamOut) > 150) { break; }
}
show('StreamSample', substr($streamOut,0,150));

show('Status','Done');
