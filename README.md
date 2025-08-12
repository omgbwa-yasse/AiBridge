# AiBridge

Unified Laravel package for interacting with multiple LLM APIs (OpenAI, Ollama, Gemini, Claude, Grok, etc.) with complete support for:

- 💬 **Conversational chat** with history
- 🌊 **Real-time streaming**
- 🔍 **Embeddings** for semantic search
- 🎨 **Image generation** (DALL-E, Stable Diffusion via Ollama)
- 🔊 **Audio** (Text-to-Speech and Speech-to-Text)
- 📋 **Structured output** (JSON mode with schema validation)
- 🛠️ **Function calling** native and generic
- 🎯 **Extensible system tools**
- 🔧 **Laravel Facade** `AiBridge` for simplified access

> ✅ **Status**: Stable - Consolidated API after fixes (v1.0)

## Installation

```bash
composer require omgbwa-yasse/aibridge
```

### Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="AiBridge\AiBridgeServiceProvider" --tag=config
```

### Environment Variables

Configure your API keys in `.env`:

```env
# OpenAI
OPENAI_API_KEY=sk-...

# Other providers
OLLAMA_ENDPOINT=http://localhost:11434
GEMINI_API_KEY=...
CLAUDE_API_KEY=...
GROK_API_KEY=...

# Custom providers (Azure OpenAI, etc.)
OPENAI_CUSTOM_API_KEY=...
OPENAI_CUSTOM_BASE_URL=https://your-azure-openai.openai.azure.com
OPENAI_CUSTOM_AUTH_HEADER=api-key
OPENAI_CUSTOM_AUTH_PREFIX=

# HTTP Configuration
LLM_HTTP_TIMEOUT=30
LLM_HTTP_RETRY=2
LLM_HTTP_RETRY_SLEEP=200
```

## Basic Usage

### Access via Laravel Container

Get the manager directly from the container:

```php
$manager = app('AiBridge'); // AiBridge\AiBridgeManager instance
$resp = $manager->chat('openai', [
    ['role' => 'user', 'content' => 'Hello']
]);
```

Or via dependency injection:

```php
use AiBridge\AiBridgeManager;

class MyService 
{
    public function __construct(private AiBridgeManager $ai) {}

    public function run(): array {
        return $this->ai->chat('openai', [ 
            ['role' => 'user', 'content' => 'Hello'] 
        ]);
    }
}
```

### Basic Chat with Facade

```php
use AiBridge\Facades\AiBridge;

$res = AiBridge::chat('openai', [
    ['role' => 'user', 'content' => 'Hello, who are you?']
]);
$text = $res['choices'][0]['message']['content'] ?? '';
```

### Laravel Alias (Optional)

The `AiBridge` facade is available via auto-discovery. For a custom alias, add to `config/app.php`:

```php
'aliases' => [
    // ...
    'AI' => AiBridge\Facades\AiBridge::class,
],
```

### Normalized Response

```php
use AiBridge\Support\ChatNormalizer;

$raw = AiBridge::chat('openai', [ 
    ['role' => 'user', 'content' => 'Hello'] 
]);
$normalized = ChatNormalizer::normalize($raw);
echo $normalized['text'];
```

## Advanced Features

### Real-time Streaming

```php
foreach (AiBridge::stream('openai', [ 
    ['role' => 'user', 'content' => 'Explain gravity in 3 points'] 
]) as $chunk) {
    echo $chunk; // flush to SSE client
}
```

### Embeddings for Semantic Search

```php
$result = AiBridge::embeddings('openai', [
    'First text to vectorize',
    'Second text to analyze'
]);
$vectors = $result['embeddings'];
```

### Image Generation

```php
$result = AiBridge::image('openai', 'An astronaut cat in space', [
    'size' => '1024x1024',
    'model' => 'dall-e-3',
    'quality' => 'hd'
]);
$imageUrl = $result['images'][0]['url'] ?? null;
```

### Audio Text-to-Speech

```php
$result = AiBridge::tts('openai', 'Hello world', [
    'voice' => 'alloy',
    'model' => 'tts-1-hd'
]);
file_put_contents('output.mp3', base64_decode($result['audio']));
```

### Audio Speech-to-Text

```php
$result = AiBridge::stt('openai', storage_path('app/audio.wav'), [
    'model' => 'whisper-1'
]);
$transcription = $result['text'];
```

## Structured Output (JSON Mode)

### With Schema Validation

```php
$res = AiBridge::chat('openai', [
    ['role' => 'user', 'content' => 'Give me person info in JSON format']
], [
    'response_format' => 'json',
    'json_schema' => [
        'name' => 'person_schema',
        'schema' => [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'number'],
                'city' => ['type' => 'string']
            ],
            'required' => ['name', 'age']
        ]
    ]
]);

// Check validation
if ($res['schema_validation']['valid'] ?? false) {
    $person = json_decode($res['choices'][0]['message']['content'], true);
    echo "Name: " . $person['name'];
} else {
    $errors = $res['schema_validation']['errors'] ?? [];
    echo "Validation errors: " . implode(', ', $errors);
}
```

### Simple JSON Mode (Ollama)

```php
$res = AiBridge::chat('ollama', [
    ['role' => 'user', 'content' => 'List 3 African countries in JSON']
], [
    'response_format' => 'json',
    'model' => 'llama3.1'
]);
```

## Function Calling

### OpenAI Native Function Calling

```php
$tools = [
    [
        'name' => 'getWeather',
        'description' => 'Get weather for a city',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'city' => ['type' => 'string', 'description' => 'City name']
            ],
            'required' => ['city']
        ]
    ]
];

$resp = AiBridge::chat('openai', [
    ['role' => 'user', 'content' => 'What\'s the weather in Paris?']
], [
    'tools' => $tools,
    'tool_choice' => 'auto'
]);

if (!empty($resp['tool_calls'])) {
    foreach ($resp['tool_calls'] as $call) {
        $functionName = $call['name'];
        $arguments = $call['arguments'];
        // Execute function...
    }
}
```

### Generic Tools System

Create a custom tool:

```php
use AiBridge\Contracts\ToolContract;

class WeatherTool implements ToolContract
{
    public function name(): string { 
        return 'get_weather'; 
    }
    
    public function description(): string { 
        return 'Get current weather for a city'; 
    }
    
    public function schema(): array { 
        return [
            'type' => 'object',
            'properties' => [
                'city' => ['type' => 'string']
            ],
            'required' => ['city']
        ]; 
    }
    
    public function execute(array $arguments): string { 
        $city = $arguments['city'] ?? 'Paris';
        // Weather API call...
        return json_encode(['city' => $city, 'temp' => '22°C']);
    }
}
```

Register and use the tool:

```php
$manager = app('AiBridge');
$manager->registerTool(new WeatherTool());

$result = $manager->chatWithTools('ollama', [
    ['role' => 'user', 'content' => 'What\'s the weather in Lyon?']
], [
    'model' => 'llama3.1',
    'max_tool_iterations' => 3
]);

echo $result['final']['message']['content'];
// Tool call history in $result['tool_calls']
```

## Supported Providers

| Provider | Chat | Stream | Embeddings | Images | Audio (TTS) | Audio (STT) | Tools |
|----------|------|--------|------------|--------|-------------|-------------|-------|
| **OpenAI** | ✅ | ✅ | ✅ | ✅ (DALL-E) | ✅ | ✅ | ✅ Native |
| **Ollama** | ✅ | ✅ | ✅ | ✅ (SD) | ❌ | ❌ | ✅ Generic |
| **Gemini** | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ Generic |
| **Claude** | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ Generic |
| **Grok** | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ Generic |
| **Custom OpenAI** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ Native |

## Advanced Configuration

### Timeouts and Retry

```env
# HTTP request timeout (seconds)
LLM_HTTP_TIMEOUT=30

# Number of retry attempts on failure
LLM_HTTP_RETRY=2

# Delay between retries (ms)
LLM_HTTP_RETRY_SLEEP=200
```

### File Security

```env
# Maximum file size (bytes)
LLM_MAX_FILE_BYTES=2097152

# Allowed MIME types for files
# (configured in config/aibridge.php)
```

### Custom Provider (Azure OpenAI)

```env
OPENAI_CUSTOM_API_KEY=your-azure-key
OPENAI_CUSTOM_BASE_URL=https://your-resource.openai.azure.com
OPENAI_CUSTOM_AUTH_HEADER=api-key
OPENAI_CUSTOM_AUTH_PREFIX=
```

## Practical Examples

### Conversational Assistant with History

```php
class ChatbotService
{
    private array $conversation = [];
    
    public function __construct(private AiBridgeManager $ai) {}
    
    public function chat(string $userMessage): string
    {
        $this->conversation[] = ['role' => 'user', 'content' => $userMessage];
        
        $response = $this->ai->chat('openai', $this->conversation, [
            'model' => 'gpt-4',
            'temperature' => 0.7
        ]);
        
        $assistantMessage = $response['choices'][0]['message']['content'];
        $this->conversation[] = ['role' => 'assistant', 'content' => $assistantMessage];
        
        return $assistantMessage;
    }
}
```

### Semantic Search with Embeddings

```php
class SemanticSearch
{
    public function __construct(private AiBridgeManager $ai) {}
    
    public function search(string $query, array $documents): array
    {
        // Vectorize query and documents
        $inputs = [$query, ...$documents];
        $result = $this->ai->embeddings('openai', $inputs);
        
        $queryVector = $result['embeddings'][0];
        $docVectors = array_slice($result['embeddings'], 1);
        
        // Calculate cosine similarity
        $similarities = [];
        foreach ($docVectors as $i => $docVector) {
            $similarities[$i] = $this->cosineSimilarity($queryVector, $docVector);
        }
        
        // Sort by relevance
        arsort($similarities);
        
        return array_map(fn($i) => [
            'document' => $documents[$i],
            'score' => $similarities[$i]
        ], array_keys($similarities));
    }
    
    private function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = array_sum(array_map(fn($x, $y) => $x * $y, $a, $b));
        $normA = sqrt(array_sum(array_map(fn($x) => $x * $x, $a)));
        $normB = sqrt(array_sum(array_map(fn($x) => $x * $x, $b)));
        
        return $dotProduct / ($normA * $normB);
    }
}
```

### Streaming for Real-time Interface

```php
Route::get('/chat-stream', function (Request $request) {
    $message = $request->input('message');
    
    return response()->stream(function () use ($message) {
        $manager = app('AiBridge');
        
        foreach ($manager->stream('openai', [
            ['role' => 'user', 'content' => $message]
        ]) as $chunk) {
            echo "data: " . json_encode(['chunk' => $chunk]) . "\n\n";
            ob_flush();
            flush();
        }
        
        echo "data: [DONE]\n\n";
    }, 200, [
        'Content-Type' => 'text/plain',
        'Cache-Control' => 'no-cache',
        'X-Accel-Buffering' => 'no'
    ]);
});
```

## Testing

Run the test suite:

```bash
composer test
```

Or via PHPUnit directly:

```bash
./vendor/bin/phpunit
```

## Development

### Contributing

1. Fork the project
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Roadmap

- [ ] Native Claude Function Calling support
- [ ] Automatic embeddings caching
- [ ] Additional providers (Cohere, Hugging Face)
- [ ] Web administration interface
- [ ] Integrated metrics and monitoring
- [ ] Advanced multimodal support (vision, audio)

## License

This package is open source under the [MIT](LICENSE) license.

## Disclaimer

This package is not officially affiliated with OpenAI, Anthropic, Google, or other mentioned providers. Please respect their respective terms of service.

## Support

- 📖 [Complete Documentation](https://github.com/omgbwa-yasse/AiBridge/wiki)
- 🐛 [Report a Bug](https://github.com/omgbwa-yasse/AiBridge/issues)
- 💬 [Discussions](https://github.com/omgbwa-yasse/AiBridge/discussions)
- ⭐ Don't forget to star the project if it helps you!
