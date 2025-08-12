<?php
// Quick runtime check for Ollama gemma model integration.
// Usage: php examples/ollama_gemma_check.php [model] [endpoint]
// Defaults: model=gemma3:4b (adjust to your local model name), endpoint=http://localhost:11434

require_once __DIR__.'/../vendor/autoload.php';

use AiBridge\AiBridgeManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Illuminate\Http\Client\Factory as HttpFactory;

$model = $argv[1] ?? 'gemma3:4b';
$endpoint = $argv[2] ?? 'http://localhost:11434';

// Minimal container for Http facade if not in Laravel
if (!Facade::getFacadeApplication()) {
    $app = new Container();
    $app->singleton('http', fn() => new HttpFactory());
    Facade::setFacadeApplication($app);
}

function out($label, $value) { fwrite(STDOUT, "[{$label}] {$value}\n"); }

// 1. Check model availability via /api/tags (list models)
try {
    $resp = Http::timeout(5)->get(rtrim($endpoint,'/').'/api/tags');
    if ($resp->successful()) {
        $tags = $resp->json();
        $names = array_map(fn($t)=>$t['name'] ?? '', $tags['models'] ?? []);
        $has = in_array($model, $names, true);
        out('ModelsFound', implode(', ', $names));
        out('ModelPresent', $has ? 'yes' : 'no');
        if (!$has) {
            out('Warning', "Model {$model} not listed; continuing anyway");
        }
    } else {
        out('Error', '/api/tags request failed '.$resp->status());
    }
} catch (Throwable $e) {
    out('Error', 'Tags check: '.$e->getMessage());
}

$config = [ 'ollama' => [ 'endpoint' => $endpoint ] ];
$manager = new AiBridgeManager($config);

// 2. Basic chat
try {
    $chat = $manager->chat('ollama', [ ['role'=>'user','content'=>'Bonjour, résume en un mot: Intelligence artificielle'] ], [ 'model' => $model ]);
    $answer = $chat['message']['content'] ?? json_encode($chat);
    out('ChatAnswer', $answer);
} catch (Throwable $e) {
    out('Error', 'Chat failed: '.$e->getMessage());
}

// 3. Streaming (collect first ~200 chars)
try {
    $chunks = '';
    foreach ($manager->stream('ollama', [ ['role'=>'user','content'=>'Explique la gravité en deux phrases.'] ], [ 'model' => $model ]) as $c) {
        $chunks .= $c;
        if (strlen($chunks) > 200) { break; }
    }
    out('StreamSample', substr($chunks,0,200));
} catch (Throwable $e) {
    out('Error', 'Stream failed: '.$e->getMessage());
}

// 4. JSON structured output (Ollama format=json)
try {
    $jsonResp = $manager->chat('ollama', [ ['role'=>'user','content'=>'Donne un objet JSON avec la liste des pays Afrique Centrale'] ], [ 'model' => $model, 'response_format' => 'json' ]);
    out('JSONRaw', ($jsonResp['message']['content'] ?? ''));
} catch (Throwable $e) {
    out('Error', 'JSON chat failed: '.$e->getMessage());
}

out('Status', 'Done');
