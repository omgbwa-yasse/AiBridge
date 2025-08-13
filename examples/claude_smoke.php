<?php
// Simple smoke test for Claude (Anthropic) messages API.
// Usage (PowerShell):
//   $env:CLAUDE_API_KEY="<your_key>"; php examples/claude_smoke.php [model]
// Or pass the key as 2nd arg: php examples/claude_smoke.php [model] <your_key>
// Default model: claude-3-5-sonnet-20240620

require_once __DIR__.'/../vendor/autoload.php';

use AiBridge\AiBridgeManager;

$model = $argv[1] ?? 'claude-3-5-sonnet-20240620';
$key = getenv('CLAUDE_API_KEY') ?: ($argv[2] ?? null);

if (!$key) {
    fwrite(STDERR, "Missing CLAUDE_API_KEY. Set it in env or pass as second argument.\n");
    exit(1);
}

$manager = new AiBridgeManager([
  'claude' => [ 'api_key' => $key ],
  'options' => [ 'default_timeout' => 30, 'retry' => ['times' => 1, 'sleep' => 200], 'verify' => false ],
]);

function show($k,$v){ echo "[$k] $v".PHP_EOL; }

$messages = [ ['role'=>'user','content'=>'Dis un mot sur le ciel.'] ];

$resp = $manager->chat('claude', $messages, [ 'model' => $model ]);
$text = $resp['content'][0]['text'] ?? json_encode($resp);
show('Chat', $text);

$streamOut = '';
foreach ($manager->stream('claude', [ ['role'=>'user','content'=>'Explique la gravité en une phrase.'] ], [ 'model' => $model ]) as $chunk) {
  $streamOut .= $chunk;
  if (strlen($streamOut) > 160) { break; }
}
show('StreamSample', substr($streamOut,0,160));

show('Status','Done');
