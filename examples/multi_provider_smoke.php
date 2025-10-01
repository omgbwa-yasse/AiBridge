<?php
// Comprehensive smoke test for multiple AI providers
// Tests: Mistral, OpenRouter, and Ollama Turbo
// Usage (PowerShell on Windows):
//   $env:MISTRAL_API_KEY = "<your_mistral_key>"
//   $env:OPENROUTER_API_KEY = "<your_openrouter_key>"
//   $env:OLLAMA_TURBO_API_KEY = "<your_ollama_turbo_key>"
//   php examples/multi_provider_smoke.php

require_once __DIR__.'/../vendor/autoload.php';

use AiBridge\AiBridgeManager;

function show($k,$v){ echo "[$k] $v".PHP_EOL; }
function testProvider($manager, $providerName, $model, $label) {
    echo "\n--- Testing $label ---\n";
    try {
        $resp = $manager->chat($providerName, [
            ['role'=>'user','content'=>'Give one word to describe the sky']
        ], ['model'=>$model]);
        show("$label Chat", $resp['choices'][0]['message']['content'] ?? $resp['message']['content'] ?? json_encode($resp));
        
        $streamOut = '';
        foreach ($manager->stream($providerName, [
            ['role'=>'user','content'=>'What is AI?']
        ], ['model'=>$model]) as $chunk) {
            $streamOut .= $chunk;
            if (strlen($streamOut) > 100) { break; }
        }
        show("$label Stream", substr($streamOut,0,100).'...');
        show("$label Status", "✓ OK");
    } catch (\Throwable $e) {
        show("$label Error", $e->getMessage());
    }
}

$config = [];

// Mistral
$mistralKey = getenv('MISTRAL_API_KEY');
if ($mistralKey) {
    $config['mistral'] = [
        'api_key' => $mistralKey,
        'endpoint' => 'https://api.mistral.ai/v1/chat/completions',
    ];
}

// OpenRouter
$openrouterKey = getenv('OPENROUTER_API_KEY');
if ($openrouterKey) {
    $config['openrouter'] = [
        'api_key' => $openrouterKey,
        'base_url' => 'https://openrouter.ai/api/v1',
        'referer' => getenv('OPENROUTER_REFERER'),
        'title' => getenv('OPENROUTER_TITLE'),
    ];
}

// Ollama Turbo
$ollamaTurboKey = getenv('OLLAMA_TURBO_API_KEY');
if ($ollamaTurboKey) {
    $config['ollama_turbo'] = [
        'api_key' => $ollamaTurboKey,
        'endpoint' => 'https://ollama.com',
    ];
}

if (empty($config)) {
    fwrite(STDERR, "No API keys found.\n\n" .
        "Set at least one of these environment variables (PowerShell):\n" .
        "  \$env:MISTRAL_API_KEY = 'your-key-here'\n" .
        "  \$env:OPENROUTER_API_KEY = 'sk-or-v1-...'\n" .
        "  \$env:OLLAMA_TURBO_API_KEY = 'sk-...'\n\n" .
        "Then run: php examples/multi_provider_smoke.php\n");
    exit(1);
}

$manager = new AiBridgeManager($config);

echo "=== Multi-Provider Smoke Test ===\n";
echo "Testing " . count($config) . " provider(s)\n";

if (isset($config['mistral'])) {
    testProvider($manager, 'mistral', 'mistral-small-latest', 'Mistral');
}

if (isset($config['openrouter'])) {
    testProvider($manager, 'openrouter', 'openai/gpt-4o-mini', 'OpenRouter');
}

if (isset($config['ollama_turbo'])) {
    testProvider($manager, 'ollama_turbo', 'gpt-oss:20b', 'Ollama Turbo');
}

echo "\n=== All Tests Completed ===\n";
