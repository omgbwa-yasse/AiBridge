<?php
// Smoke test for Claude using per-call override (no pre-config in manager)
// Usage: php examples/claude_override_smoke.php [api_key] [model]
// Defaults: model=claude-3-5-sonnet-20240620

require_once __DIR__.'/../vendor/autoload.php';

use AiBridge\AiBridgeManager;

$key = $argv[1] ?? getenv('CLAUDE_API_KEY') ?: null;
$model = $argv[2] ?? 'claude-3-5-sonnet-20240620';

if (!$key) {
    fwrite(STDERR, "Missing CLAUDE_API_KEY. Pass as first arg or set env.\n");
    exit(1);
}

$ai = new AiBridgeManager([]);

function out($k,$v){ echo "[$k] ".$v.PHP_EOL; }

$messages = [ ['role' => 'user', 'content' => 'Dis un mot sur la lune.'] ];

try {
    // Chat with per-call API key
    $resp = $ai->chat('claude', $messages, [ 'api_key' => $key, 'model' => $model ]);
    $text = $resp['content'][0]['text'] ?? json_encode($resp);
    out('Chat', $text);

    // Streaming (first ~160 chars)
    $buf = '';
    foreach ($ai->stream('claude', [ ['role' => 'user', 'content' => 'Explique le vol des oiseaux en une phrase.'] ], [ 'api_key' => $key, 'model' => $model ]) as $chunk) {
        $buf .= $chunk;
        if (strlen($buf) >= 160) { break; }
    }
    out('StreamSample', substr($buf,0,160));

    out('Status', 'Done');
} catch (Throwable $e) {
    out('Error', $e->getMessage());
    exit(1);
}
