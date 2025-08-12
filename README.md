# LLM CHAT

Package Laravel unifié pour interagir avec plusieurs LLM / APIs (OpenAI, Ollama, Gemini, Claude, Grok, etc.) avec support :

- Chat + Historique
- Streaming
- Embeddings
- Génération d'images
- Audio (TTS / STT)
- Structured output (mode JSON simplifié)
- Facade `AIChat`

> Statut: EXPÉRIMENTAL (API susceptible de changer)

## Installation

```bash
composer require aibridge/llm-chat
```

Publier la config :

```bash
php artisan vendor:publish --provider="AiBridge\AIChatServiceProvider" --tag=config
```

Configurer vos variables `.env` :

```env
OPENAI_API_KEY=sk-...
GEMINI_API_KEY=...
CLAUDE_API_KEY=...
GROK_API_KEY=...
```

### Accès via le conteneur (app helper)

Outre la façade `AIChat`, vous pouvez récupérer le manager directement depuis le conteneur en utilisant la clé de service `AiBridge` :

```php
$manager = app('AiBridge'); // instance de AiBridge\AiBridgeManager
$resp = $manager->chat('openai', [
	['role' => 'user', 'content' => 'Bonjour']
]);
```

Si vous préférez l’injection de dépendances, vous pouvez aussi typer `AiBridge\AIChatManager` dans vos constructeurs (Laravel résoudra la classe) :

```php
use AiBridge\AiBridgeManager;

class MyService {
	public function __construct(private AiBridgeManager $ai) {}

	public function run(): array {
		return $this->ai->chat('openai', [ ['role' => 'user', 'content' => 'Hello'] ]);
	}
}
```

## Chat basique

```php
use AiBridge\Facades\AIChat;

$res = AIChat::chat('openai', [
	['role' => 'user', 'content' => 'Bonjour, qui es-tu ?']
]);
$text = $res['choices'][0]['message']['content'] ?? '';
```

### Alias Laravel (facultatif)

La façade `AIChat` est disponible via auto-discovery du service provider. Si votre application désactive l’auto-discovery ou si vous souhaitez déclarer un alias manuel, ajoutez l’alias suivant à `config/app.php` :

```php
'aliases' => [
	// ...
	'AIChat' => AiBridge\Facades\AIChat::class,
],
```

### Réponse normalisée

```php
use AiBridge\Support\ChatNormalizer;
$raw = AIChat::chat('openai', [ ['role' => 'user', 'content' => 'Bonjour'] ]);
$normalized = ChatNormalizer::normalize($raw);
echo $normalized['text'];
```

## Streaming

```php
foreach (AIChat::stream('openai', [ ['role' => 'user', 'content' => 'Explique la gravité en 3 points'] ]) as $chunk) {
	echo $chunk; // flush vers client SSE
}
```

## Embeddings

```php
$vec = AIChat::embeddings('openai', 'Texte à vectoriser');
```

## Image

```php
$imageB64 = AIChat::image('openai', 'Un chat astronaute');
```

## Audio TTS

```php
$mp3 = AIChat::tts('openai', 'Bonjour le monde');
file_put_contents('sortie.mp3', base64_decode($mp3['audio'] ?? ''));
```

## Audio STT

```php
$text = AIChat::stt('openai', storage_path('app/audio.wav'));
```

## Structured Output (JSON Mode simple)

```php
$res = AIChat::chat('openai', [
	['role' => 'user', 'content' => 'Donne un JSON avec name et age']
], [
	'response_format' => 'json',
	'json_schema' => [
		'name' => 'person_schema',
		'schema' => [
			'type' => 'object',
			'properties' => [
				'name' => ['type' => 'string'],
				'age' => ['type' => 'number']
			],
			'required' => ['name','age']
		]
	]
]);
```

Pass `response_format => 'json'` and optionally `json_schema` (OpenAI json schema). The response will include `schema_validation` with validation status.

Example:

```php
$resp = AIChat::chat('openai', $messages, [
	'response_format' => 'json',
	'json_schema' => [
		'name' => 'user_profile',
		'schema' => [
			'type' => 'object',
			'required' => ['name','age'],
			'properties' => [
				'name' => ['type' => 'string'],
				'age' => ['type' => 'number']
			]
		]
	]
]);
// $resp['schema_validation'] = ['valid' => true] or includes errors
```

## Native OpenAI Function Calling

Provide a `tools` array (each with name, description, parameters/schema) and optional `tool_choice` => 'auto'. Returned assistant message will contain normalized `tool_calls` array for easier post-processing.

```php
$tools = [
	[
		'name' => 'getWeather',
		'description' => 'Get weather for a city',
		'parameters' => [
			'type' => 'object',
			'properties' => ['city' => ['type' => 'string']],
			'required' => ['city']
		]
	]
];

$resp = AIChat::chat('openai', $messages, [ 'tools' => $tools, 'tool_choice' => 'auto' ]);
if (!empty($resp['tool_calls'])) {
	// execute
}
```

## Outils (Tool Calling générique)

Enregistrez un outil :

```php
use AiBridge\Tools\SystemInfoTool;

AIChat::registerTool(new SystemInfoTool());
```

Lancer une conversation avec outils (itérations automatiques jusqu'à réponse finale) :

```php
$result = app(\AiBridge\AiBridgeManager::class)->chatWithTools('ollama', [
	['role' => 'user', 'content' => "Donne la version PHP via l'outil puis explique en une phrase."]
]);

// $result['tool_calls'] contient les exécutions, $result['final'] la dernière réponse brute provider
```

### Timeouts & Retry

Configurer via .env :

```env
LLM_HTTP_TIMEOUT=30
LLM_HTTP_RETRY=2
LLM_HTTP_RETRY_SLEEP=200
```

Ces valeurs s’appliquent aux requêtes HTTP (retry + timeout) lors de l’initialisation du manager.

## Ollama Exemple

```php
$stream = AIChat::stream('ollama', [ ['role' => 'user', 'content' => 'Décris la mer'] ], ['model' => 'llama2']);
foreach ($stream as $part) {
	echo $part;
}
```

## Roadmap

- Tools / function calling abstrait
- Validation stricte schema JSON
- Multi-tenancy provider config override
- Caching embeddings
- Tests intégrés

## Avertissement

Ce package n'est pas affilié aux fournisseurs cités. Respectez leurs conditions d'utilisation.
