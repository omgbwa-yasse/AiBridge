<?php
// Simple smoke test for OpenRouter (OpenAI-compatible /api/v1).
// Usage (PowerShell on Windows):
//   $env:OPENROUTER_API_KEY = "<your_api_key>"
//   php examples/openrouter_smoke.php [model]
// Or pass the API key as the 2nd arg:
//   php examples/openrouter_smoke.php [model] [apiKey]
// Default model: openai/gpt-4o

require_once __DIR__.'/../vendor/autoload.php';

use AiBridge\AiBridgeManager;

$model = $argv[1] ?? 'openai/gpt-4o';
$key = getenv('OPENROUTER_API_KEY') ?: ($argv[2] ?? null);
$referer = getenv('OPENROUTER_REFERER') ?: null; // optional
$title = getenv('OPENROUTER_TITLE') ?: null;     // optional

if (!$key) {
    fwrite(STDERR, "Missing OPENROUTER_API_KEY.\n\n" .
        "Set it and rerun, for example (PowerShell):\n" .
        "  \$env:OPENROUTER_API_KEY = 'sk-or-v1-...'\n" .
        "  php examples/openrouter_smoke.php\n\n" .
        "Alternatively, pass it as the 2nd argument:\n" .
        "  php examples/openrouter_smoke.php openai/gpt-4o sk-or-v1-...\n\n" .
        "Get your API key at: https://openrouter.ai/keys\n");
    exit(1);
}

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

try {
    $resp = $manager->chat('openrouter', $messages, [ 'model' => $model ]);
    show('Chat', $resp['choices'][0]['message']['content'] ?? json_encode($resp));
} catch (\Throwable $e) {
    show('ChatError', $e->getMessage());
    exit(1);
}

try {
    $streamOut = '';
    foreach ($manager->stream('openrouter', [ ['role'=>'user','content'=>'Explain quantum computing.'] ], [ 'model' => $model ]) as $chunk) {
        $streamOut .= $chunk;
        if (strlen($streamOut) > 150) { break; }
    }
    show('StreamSample', substr($streamOut,0,150));
} catch (\Throwable $e) {
    show('StreamError', $e->getMessage());
    exit(1);
}

show('Status','Done');

