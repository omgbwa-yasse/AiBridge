<?php
// Simple smoke test for Mistral AI provider (https://mistral.ai).
// Usage (PowerShell on Windows):
//   $env:MISTRAL_API_KEY = "<your_api_key>"
//   php examples/mistral_smoke.php [model] [endpoint]
// Or pass the API key as the 3rd arg:
//   php examples/mistral_smoke.php [model] [endpoint] [apiKey]
// Defaults: model=mistral-small-latest, endpoint=https://api.mistral.ai/v1/chat/completions

require_once __DIR__.'/../vendor/autoload.php';

use AiBridge\AiBridgeManager;

$model = $argv[1] ?? 'mistral-small-latest';
$endpoint = $argv[2] ?? 'https://api.mistral.ai/v1/chat/completions';
$apiKey = getenv('MISTRAL_API_KEY') ?: ($argv[3] ?? null);

if (!$apiKey) {
    fwrite(STDERR, "Missing MISTRAL_API_KEY.\n\n" .
        "Set it and rerun, for example (PowerShell):\n" .
        "  \$env:MISTRAL_API_KEY = 'your-key-here'\n" .
        "  php examples/mistral_smoke.php\n\n" .
        "Alternatively, pass it as the 3rd argument:\n" .
        "  php examples/mistral_smoke.php mistral-small-latest https://api.mistral.ai/v1/chat/completions your-key-here\n");
    exit(1);
}

$manager = new AiBridgeManager([
  'mistral' => [
    'endpoint' => $endpoint,
    'api_key' => $apiKey,
  ],
]);

function show($k,$v){ echo "[$k] $v".PHP_EOL; }

// Basic chat
try {
    $resp = $manager->chat('mistral', [ ['role'=>'user','content'=>'Give one word to describe the sky']] , ['model'=>$model]);
    show('Chat', $resp['choices'][0]['message']['content'] ?? json_encode($resp));
} catch (\Throwable $e) {
    show('ChatError', $e->getMessage());
    exit(1);
}

// Streaming sample (first 150 chars)
try {
    $streamOut = '';
    foreach ($manager->stream('mistral', [ ['role'=>'user','content'=>'Explain photosynthesis briefly.'] ], ['model'=>$model]) as $chunk) {
        $streamOut .= $chunk;
        if (strlen($streamOut) > 150) { break; }
    }
    show('StreamSample', substr($streamOut,0,150));
} catch (\Throwable $e) {
    show('StreamError', $e->getMessage());
    exit(1);
}

show('Status','Done');
