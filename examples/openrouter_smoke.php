<?php
// Simple smoke test for OpenRouter (OpenAI-compatible /api/v1).
// Usage:
//   set OPENROUTER_API_KEY in your environment, then:
//     php examples/openrouter_smoke.php [model]
// Default model: openai/gpt-4o

require_once __DIR__.'/../vendor/autoload.php';

use AiBridge\AiBridgeManager;

$model = $argv[1] ?? 'openai/gpt-4o';
$key = getenv('OPENROUTER_API_KEY') ?: ($argv[2] ?? null);
$referer = getenv('OPENROUTER_REFERER') ?: null; // optional
$title = getenv('OPENROUTER_TITLE') ?: null;     // optional

$manager = new AiBridgeManager([
  'openrouter' => [
    'api_key' => $key,
    'base_url' => 'https://openrouter.ai/api/v1',
    'referer' => $referer,
    'title' => $title,
  ],
]);

function show($k,$v){ echo "[$k] $v".PHP_EOL; }

$messages = [ ['role'=>'user','content'=>'Say one word about the sky']] ;

$resp = $manager->chat('openrouter', $messages, [ 'model' => $model ]);
show('Chat', $resp['choices'][0]['message']['content'] ?? json_encode($resp));

$streamOut = '';
foreach ($manager->stream('openrouter', [ ['role'=>'user','content'=>'Explain quantum computing.'] ], [ 'model' => $model ]) as $chunk) {
  $streamOut .= $chunk;
  if (strlen($streamOut) > 150) { break; }
}
show('StreamSample', substr($streamOut,0,150));

show('Status','Done');
