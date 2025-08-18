# AiBridge

Package Laravel unifié pour interagir avec plusieurs LLM / APIs (OpenAI, Ollama, Gemini, Claude, Grok, etc.) avec support complet pour :

- 💬 **Chat conversationnel** avec historique
- 🌊 **Streaming** en temps réel
- 🔍 **Embeddings** pour la recherche sémantique
- 🎨 **Génération d'images** (DALL-E, Stable Diffusion via Ollama)
- 🔊 **Audio** (Text-to-Speech et Speech-to-Text)
- 📋 **Structured output** (mode JSON avec validation de schéma)
- 🛠️ **Function calling** natif et générique
- 🎯 **Tools système** extensibles
- 🔧 **Facade Laravel** `AiBridge` pour un accès simplifié

> ✅ **Statut**: Stable - API consolidée après corrections (v1.0)

## Installation

```bash
composer require laravel/aibridge
```

### Configuration

Publier le fichier de configuration :

```bash
php artisan vendor:publish --provider="AiBridge\AiBridgeServiceProvider" --tag=config
```

### Variables d'environnement

Configurer vos clés API dans `.env` :

```env
# OpenAI
OPENAI_API_KEY=sk-...

# Autres providers
OLLAMA_ENDPOINT=http://localhost:11434
GEMINI_API_KEY=...
CLAUDE_API_KEY=...
GROK_API_KEY=...

# Providers personnalisés (Azure OpenAI, etc.)
OPENAI_CUSTOM_API_KEY=...
OPENAI_CUSTOM_BASE_URL=https://your-azure-openai.openai.azure.com
OPENAI_CUSTOM_AUTH_HEADER=api-key
OPENAI_CUSTOM_AUTH_PREFIX=

# Configuration HTTP
LLM_HTTP_TIMEOUT=30
LLM_HTTP_RETRY=2
LLM_HTTP_RETRY_SLEEP=200
```

## Utilisation de base

### Accès via le conteneur Laravel

Récupérer le manager directement depuis le conteneur :

```php
$manager = app('AiBridge'); // instance de AiBridge\AiBridgeManager
$resp = $manager->chat('openai', [
    ['role' => 'user', 'content' => 'Bonjour']
]);
```

Ou via l'injection de dépendances :

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

### Chat basique avec facade

```php
use AiBridge\Facades\AiBridge;

$res = AiBridge::chat('openai', [
    ['role' => 'user', 'content' => 'Bonjour, qui es-tu ?']
]);
$text = $res['choices'][0]['message']['content'] ?? '';
```

### Alias Laravel (facultatif)

La façade `AiBridge` est disponible via auto-discovery. Pour un alias personnalisé, ajoutez à `config/app.php` :

```php
'aliases' => [
    // ...
    'AI' => AiBridge\Facades\AiBridge::class,
],
```

### Réponse normalisée

```php
use AiBridge\Support\ChatNormalizer;

$raw = AiBridge::chat('openai', [ 
    ['role' => 'user', 'content' => 'Bonjour'] 
]);
$normalized = ChatNormalizer::normalize($raw);
echo $normalized['text'];
```

## Fonctionnalités avancées

### Builder fluide pour le texte (v2.1+)

Utilisez des méthodes courtes et explicites au lieu de grands tableaux d'options :

```php
use AiBridge\Facades\AiBridge;

$out = AiBridge::text()
    ->using('claude', 'claude-3-5-sonnet-20240620', [ 'api_key' => env('CLAUDE_API_KEY') ])
    ->withSystemPrompt('Tu es concis.')
    ->withPrompt('Explique la gravité en une phrase.')
    ->withMaxTokens(64)
    ->usingTemperature(0.2)
    ->asText();

echo $out['text'];
```

- `using(provider, model, config)` définit le provider, le modèle et une config par appel (clé API, endpoint...).
- `withPrompt` ajoute un message utilisateur ; `withSystemPrompt` ajoute un message système en tête.
- `withMaxTokens`, `usingTemperature`, `usingTopP` contrôlent la génération.
- `asText()` renvoie un tableau normalisé (`text`, `raw`, `usage`, `finish_reason`).
- `asRaw()` renvoie la réponse brute ; `asStream()` fournit des chunks en streaming.

Cette API complète l'API classique et réduit les erreurs liées aux grands tableaux d'options.

### Streaming en temps réel

```php
foreach (AiBridge::stream('openai', [ 
    ['role' => 'user', 'content' => 'Explique la gravité en 3 points'] 
]) as $chunk) {
    echo $chunk; // flush vers client SSE
}
```

### Embeddings pour la recherche sémantique

```php
$result = AiBridge::embeddings('openai', [
    'Premier texte à vectoriser',
    'Deuxième texte à analyser'
]);
$vectors = $result['embeddings'];
```

### Génération d'images

```php
$result = AiBridge::image('openai', 'Un chat astronaute dans l\'espace', [
    'size' => '1024x1024',
    'model' => 'dall-e-3',
    'quality' => 'hd'
]);
$imageUrl = $result['images'][0]['url'] ?? null;
```

### Audio Text-to-Speech

```php
$result = AiBridge::tts('openai', 'Bonjour le monde', [
    'voice' => 'alloy',
    'model' => 'tts-1-hd'
]);
file_put_contents('sortie.mp3', base64_decode($result['audio']));
```

### Audio Speech-to-Text

```php
$result = AiBridge::stt('openai', storage_path('app/audio.wav'), [
    'model' => 'whisper-1'
]);
$transcription = $result['text'];
```

## Structured Output (JSON Mode)

### Avec validation de schéma

```php
$res = AiBridge::chat('openai', [
    ['role' => 'user', 'content' => 'Donne-moi les infos d\'une personne en JSON']
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

// Vérifier la validation
if ($res['schema_validation']['valid'] ?? false) {
    $person = json_decode($res['choices'][0]['message']['content'], true);
    echo "Nom: " . $person['name'];
} else {
    $errors = $res['schema_validation']['errors'] ?? [];
    echo "Erreurs de validation: " . implode(', ', $errors);
}
```

### Mode JSON simple (Ollama)

```php
$res = AiBridge::chat('ollama', [
    ['role' => 'user', 'content' => 'Liste 3 pays africains en JSON']
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
        'description' => 'Obtenir la météo d\'une ville',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'city' => ['type' => 'string', 'description' => 'Nom de la ville']
            ],
            'required' => ['city']
        ]
    ]
];

$resp = AiBridge::chat('openai', [
    ['role' => 'user', 'content' => 'Quelle est la météo à Paris ?']
], [
    'tools' => $tools,
    'tool_choice' => 'auto'
]);

if (!empty($resp['tool_calls'])) {
    foreach ($resp['tool_calls'] as $call) {
        $functionName = $call['name'];
        $arguments = $call['arguments'];
        // Exécuter la fonction...
    }
}
```

### Système d'outils génériques

Créer un outil personnalisé :

```php
use AiBridge\Contracts\ToolContract;

class WeatherTool implements ToolContract
{
    public function name(): string { 
        return 'get_weather'; 
    }
    
    public function description(): string { 
        return 'Obtenir la météo actuelle d\'une ville'; 
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
        // Appel API météo...
        return json_encode(['city' => $city, 'temp' => '22°C']);
    }
}
```

Enregistrer et utiliser l'outil :

```php
$manager = app('AiBridge');
$manager->registerTool(new WeatherTool());

$result = $manager->chatWithTools('ollama', [
    ['role' => 'user', 'content' => 'Quelle est la météo à Lyon ?']
], [
    'model' => 'llama3.1',
    'max_tool_iterations' => 3
]);

echo $result['final']['message']['content'];
// Historique des appels d'outils dans $result['tool_calls']
```

## Providers supportés

| Provider | Chat | Stream | Embeddings | Images | Audio (TTS) | Audio (STT) | Tools |
|----------|------|--------|------------|--------|-------------|-------------|-------|
| **OpenAI** | ✅ | ✅ | ✅ | ✅ (DALL-E) | ✅ | ✅ | ✅ Natif |
| **Ollama** | ✅ | ✅ | ✅ | ✅ (SD) | ❌ | ❌ | ✅ Générique |
| **Gemini** | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ Générique |
| **Claude** | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ Générique |
| **Grok** | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ Générique |
| **Custom OpenAI** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ Natif |

## Configuration avancée

### Timeouts et retry

```env
# Timeout des requêtes HTTP (secondes)
LLM_HTTP_TIMEOUT=30

# Nombre de tentatives en cas d'échec
LLM_HTTP_RETRY=2

# Délai entre les tentatives (ms)
LLM_HTTP_RETRY_SLEEP=200
```

### Sécurité des fichiers

```env
# Taille max des fichiers (bytes)
LLM_MAX_FILE_BYTES=2097152

# Types MIME autorisés pour les fichiers
# (configuré dans config/aibridge.php)
```

### Provider personnalisé (Azure OpenAI)

```env
OPENAI_CUSTOM_API_KEY=your-azure-key
OPENAI_CUSTOM_BASE_URL=https://your-resource.openai.azure.com
OPENAI_CUSTOM_AUTH_HEADER=api-key
OPENAI_CUSTOM_AUTH_PREFIX=
```

## Exemples pratiques

### Assistant conversationnel avec historique

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

### Recherche sémantique avec embeddings

```php
class SemanticSearch
{
    public function __construct(private AiBridgeManager $ai) {}
    
    public function search(string $query, array $documents): array
    {
        // Vectoriser la requête et les documents
        $inputs = [$query, ...$documents];
        $result = $this->ai->embeddings('openai', $inputs);
        
        $queryVector = $result['embeddings'][0];
        $docVectors = array_slice($result['embeddings'], 1);
        
        // Calculer la similarité cosinus
        $similarities = [];
        foreach ($docVectors as $i => $docVector) {
            $similarities[$i] = $this->cosineSimilarity($queryVector, $docVector);
        }
        
        // Trier par pertinence
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

### Streaming pour interface temps réel

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

## Tests

Exécuter la suite de tests :

```bash
composer test
```

Ou via PHPUnit directement :

```bash
./vendor/bin/phpunit
```

## Surcharges par appel (v2.0+)

Vous pouvez désormais fournir les paramètres des providers directement lors de l'appel, sans modifier la config/env :

- OpenAI: `api_key`, `chat_endpoint` (optionnel)
- Ollama: `endpoint`
- Ollama Turbo: `api_key`, `endpoint` (optionnel)
- Claude/Grok/ONN/Gemini: `api_key`, `endpoint` (optionnel)
- OpenAI compatible: `api_key`, `base_url`, et éventuellement `paths`, `auth_header`, `auth_prefix`, `extra_headers`

Exemples:

```php
$res = app('AiBridge')->chat('ollama', $messages, [
    'endpoint' => 'http://localhost:11434',
    'model' => 'llama3.1'
]);

$res = app('AiBridge')->chat('openai', $messages, [
    'api_key' => env('OPENAI_API_KEY'),
    'chat_endpoint' => 'https://api.openai.com/v1/chat/completions',
]);

$res = app('AiBridge')->chat('openai_custom', $messages, [
    'api_key' => 'ollama',
    'base_url' => 'http://localhost:11434/v1',
    'paths' => ['chat' => '/chat/completions']
]);
```

Voir `CHANGELOG.md` pour le détail des changements de la v2.0.

## Développement

### Contribution

1. Fork du projet
2. Créer une branche feature (`git checkout -b feature/amazing-feature`)
3. Commit des changements (`git commit -m 'Add amazing feature'`)
4. Push vers la branche (`git push origin feature/amazing-feature`)
5. Ouvrir une Pull Request

### Roadmap

- [ ] Support natif Claude Function Calling
- [ ] Cache automatique des embeddings
- [ ] Providers supplémentaires (Cohere, Hugging Face)
- [ ] Interface web d'administration
- [ ] Métriques et monitoring intégrés
- [ ] Support multimodal avancé (vision, audio)

## Licence

Ce package est open source sous licence [MIT](LICENSE).

## Avertissement

Ce package n'est pas officiellement affilié à OpenAI, Anthropic, Google, ou autres fournisseurs mentionnés. Respectez leurs conditions d'utilisation respectives.

## Support

- 📖 [Documentation complète](https://github.com/omgbwa-yasse/AiBridge/wiki)
- 🐛 [Signaler un bug](https://github.com/omgbwa-yasse/AiBridge/issues)
- 💬 [Discussions](https://github.com/omgbwa-yasse/AiBridge/discussions)
- ⭐ N'oubliez pas de donner une étoile si ce projet vous aide !
