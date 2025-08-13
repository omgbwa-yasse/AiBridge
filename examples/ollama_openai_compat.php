<?php
// Example: Use AiBridge CustomOpenAIProvider against Ollama's OpenAI-compatible API.
// Requirements: Ollama running locally (default http://localhost:11434), pull a model:
//   ollama pull llama3.2
// Run: php examples/ollama_openai_compat.php [model] [base_url]
// Defaults: model=llama3.2 base_url=http://localhost:11434/v1

require_once __DIR__.'/../vendor/autoload.php';

use AiBridge\AiBridgeManager;

$model = $argv[1] ?? 'llama3.2';
$baseUrl = $argv[2] ?? 'http://localhost:11434/v1';

$ai = new AiBridgeManager([
  'openai_custom' => [
    'api_key' => 'ollama', // required by client but ignored by Ollama
    'base_url' => rtrim($baseUrl,'/'),
    'paths' => [
      'chat' => '/v1/chat/completions',
      'embeddings' => '/v1/embeddings',
    ],
  ],
]);

function out($k,$v){ echo "[$k] ".$v.PHP_EOL; }

// Chat
$chat = $ai->chat('openai_custom', [
  ['role' => 'user', 'content' => 'Say this is a test'],
], [ 'model' => $model ]);
out('Chat', $chat['choices'][0]['message']['content'] ?? json_encode($chat));

// Streaming (first ~200 chars)
$buf = '';
foreach ($ai->stream('openai_custom', [
  ['role' => 'user', 'content' => 'Explain what a black hole is in one paragraph.']
], [ 'model' => $model ]) as $chunk) {
  $buf .= $chunk;
  if (strlen($buf) >= 200) {
    break;
  }
}
out('StreamSample', substr($buf,0,200));

// Embeddings
$emb = $ai->embeddings('openai_custom', [
  'why is the sky blue?',
  'why is the grass green?',
], [ 'model' => 'all-minilm' ]);
out('EmbDims', count($emb['embeddings'][0] ?? []));

out('Status', 'Done');
