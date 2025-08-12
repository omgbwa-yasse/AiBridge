<?php

namespace AiBridge;

use AiBridge\Providers\OpenAIProvider;
use AiBridge\Providers\OllamaProvider;
use AiBridge\Providers\OnnProvider;
use AiBridge\Providers\GeminiProvider;
use AiBridge\Providers\GrokProvider;
use AiBridge\Providers\ClaudeProvider;
use AiBridge\Providers\CustomOpenAIProvider;
use AiBridge\Contracts\ChatProviderContract;
use AiBridge\Contracts\EmbeddingsProviderContract;
use AiBridge\Contracts\ImageProviderContract;
use AiBridge\Contracts\AudioProviderContract;
use AiBridge\Support\Exceptions\ProviderException;
use AiBridge\Support\ToolRegistry;
use AiBridge\Contracts\ToolContract;

class AiBridgeManager
{
	protected $providers = [];
	protected ToolRegistry $toolRegistry;
	protected array $options = [];

	public function __construct(array $config)
	{
		$this->toolRegistry = new ToolRegistry();
		$this->options = $config['options'] ?? [];

		if (!empty($config['openai']['api_key'])) {
			$this->providers['openai'] = new OpenAIProvider($config['openai']['api_key']);
		}
		if (!empty($config['ollama']['endpoint'])) {
			$this->providers['ollama'] = new OllamaProvider($config['ollama']['endpoint']);
		}
		if (!empty($config['onn']['api_key'])) {
			$this->providers['onn'] = new OnnProvider($config['onn']['api_key']);
		}
		if (!empty($config['gemini']['api_key'])) {
			$this->providers['gemini'] = new GeminiProvider($config['gemini']['api_key']);
		}
		if (!empty($config['grok']['api_key'])) {
			$this->providers['grok'] = new GrokProvider($config['grok']['api_key']);
		}
		if (!empty($config['claude']['api_key'])) {
			$this->providers['claude'] = new ClaudeProvider($config['claude']['api_key']);
		}
		if (!empty($config['openai_custom']['api_key']) && !empty($config['openai_custom']['base_url'])) {
			$c = $config['openai_custom'];
			$this->providers['openai_custom'] = new CustomOpenAIProvider(
				$c['api_key'],
				$c['base_url'],
				$c['paths'] ?? [],
				$c['auth_header'] ?? 'Authorization',
				$c['auth_prefix'] ?? 'Bearer ',
				$c['extra_headers'] ?? []
			);
		}
	}

	/**
	 * Apply global HTTP options (retry/timeout) to an Illuminate Http pending request.
	 */
	public function decorateHttp($pending)
	{
		$retry = $this->options['retry']['times'] ?? 0;
		$sleep = $this->options['retry']['sleep'] ?? 0;
		$timeout = $this->options['default_timeout'] ?? null;
		if ($retry > 0) {
			$pending = $pending->retry($retry, $sleep);
		}
		if ($timeout) {
			$pending = $pending->timeout($timeout);
		}
		return $pending;
	}

	public function provider(string $name): ?ChatProviderContract
	{
		$p = $this->providers[$name] ?? null;
		return $p instanceof ChatProviderContract ? $p : null;
	}

	public function chat(string $provider, array $messages, array $options = []): array
	{
		$p = $this->providers[$provider] ?? null;
		if (!$p || !$p instanceof ChatProviderContract) {
			throw ProviderException::unsupported($provider, 'chat');
		}
		return $p->chat($messages, $options);
	}

	public function stream(string $provider, array $messages, array $options = []): \Generator
	{
		$p = $this->providers[$provider] ?? null;
		if (!$p || !$p instanceof ChatProviderContract || !$p->supportsStreaming()) {
			throw ProviderException::unsupported($provider, 'streaming');
		}
		return $p->stream($messages, $options);
	}

	public function embeddings(string $provider, array $inputs, array $options = []): array
	{
		$p = $this->providers[$provider] ?? null;
		if (!$p || !$p instanceof EmbeddingsProviderContract) {
			throw ProviderException::unsupported($provider, 'embeddings');
		}
		return $p->embeddings($inputs, $options);
	}

	public function image(string $provider, string $prompt, array $options = []): array
	{
		$p = $this->providers[$provider] ?? null;
		if (!$p || !$p instanceof ImageProviderContract) {
			throw ProviderException::unsupported($provider, 'image');
		}
		return $p->generateImage($prompt, $options);
	}

	public function tts(string $provider, string $text, array $options = []): array
	{
		$p = $this->providers[$provider] ?? null;
		if (!$p || !$p instanceof AudioProviderContract) {
			throw ProviderException::unsupported($provider, 'tts');
		}
		return $p->textToSpeech($text, $options);
	}

	public function stt(string $provider, string $path, array $options = []): array
	{
		$p = $this->providers[$provider] ?? null;
		if (!$p || !$p instanceof AudioProviderContract) {
			throw ProviderException::unsupported($provider, 'stt');
		}
		return $p->speechToText($path, $options);
	}

	// Tools API
	public function registerTool(ToolContract $tool): self
	{
		$this->toolRegistry->register($tool);
		return $this;
	}

	public function tool(string $name): ?ToolContract
	{
		return $this->toolRegistry->get($name);
	}

	public function tools(): array
	{
		return $this->toolRegistry->all();
	}

	/**
	 * Tool-aware chat loop (provider-agnostic fallback prompting strategy for providers without native tool APIs like Ollama).
	 * Strategy:
	 *  - Inject a system message enumerating available tools (name, description, JSON schema of parameters)
	 *  - Ask model to either answer normally OR respond with strict JSON: {"tool_calls":[{"name":"toolName","arguments":{...}}]}
	 *  - If tool_calls detected, execute each, append results as messages and continue until model returns normal text or max iterations.
	 */
	public function chatWithTools(string $provider, array $messages, array $options = []): array
	{
		if (empty($this->tools())) {
			return [ 'final' => $this->chat($provider, $messages, $options), 'tool_calls' => [] ];
		}
		$messages = $this->injectToolInstructionIfMissing($messages);
		$state = [
			'tool_calls' => [],
			'messages' => $messages,
			'iterations' => 0,
			'max' => $options['max_tool_iterations'] ?? 5,
			'provider' => $provider,
			'options' => $options,
			'final' => null,
			'done' => false,
		];
		while (!$state['done'] && $state['iterations'] < $state['max']) {
			$this->toolLoopIteration($state);
		}
		if (!$state['final']) {
			return [ 'error' => 'tool_iteration_limit_reached', 'tool_calls' => $state['tool_calls'] ];
		}
		return [ 'final' => $state['final'], 'tool_calls' => $state['tool_calls'] ];
	}

	protected function injectToolInstructionIfMissing(array $messages): array
	{
		$instruction = $this->buildToolInstruction($this->tools());
		foreach ($messages as $m) {
			if (($m['role'] ?? null) === 'system' && str_contains($m['content'] ?? '', 'Outils:')) {
				return $messages;
			}
		}
		array_unshift($messages, [ 'role' => 'system', 'content' => $instruction ]);
		return $messages;
	}

	protected function toolLoopIteration(array &$state): void
	{
		$state['iterations']++;
		$response = $this->chat($state['provider'], $state['messages'], $state['options']);
		$assistant = $this->extractAssistantContent($response);
		if ($assistant === null) {
			$state['final'] = $response; $state['done'] = true; return;
		}
		$toolCalls = $this->parseToolCalls($assistant);
		if (empty($toolCalls)) {
			$state['final'] = $response; $state['done'] = true; return;
		}
		$executed = $this->executeToolCalls($toolCalls);
		foreach ($executed as $call) {
			$state['tool_calls'][] = $call;
			$state['messages'][] = [ 'role' => 'tool', 'name' => $call['name'], 'content' => $call['result'] ];
		}
		$state['messages'][] = [ 'role' => 'user', 'content' => 'Si d\'autres outils sont nécessaires répond uniquement en JSON tool_calls; sinon réponds normalement.' ];
	}

	protected function executeToolCalls(array $toolCalls): array
	{
		$out = [];
		foreach ($toolCalls as $call) {
			$name = $call['name'] ?? null;
			$args = $call['arguments'] ?? [];
			if (!$name) { continue; }
			$tool = $this->tool($name);
			if (!$tool) { continue; }
			try {
				$result = $tool->execute(is_array($args) ? $args : []);
			} catch (\Throwable $e) {
				$result = 'Tool execution error: '.$e->getMessage();
			}
			$out[] = [ 'name' => $name, 'arguments' => $args, 'result' => $result ];
		}
		return $out;
	}

	protected function buildToolInstruction(array $tools): string
	{
		$specs = [];
		foreach ($tools as $tool) {
			$specs[] = [
				'name' => $tool->name(),
				'description' => $tool->description(),
				'schema' => $tool->schema(),
			];
		}
		return "Tu disposes des outils suivants. Pour demander l'exécution d'un outil, répond STRICTEMENT avec un JSON de la forme {\"tool_calls\":[{\"name\":\"toolName\",\"arguments\":{...}}]} sans texte additionnel. Outils: " . json_encode($specs, JSON_UNESCAPED_UNICODE);
	}

	protected function ensureToolSystemMessage(array $messages, string $instruction): array
	{
		foreach ($messages as $m) {
			if (($m['role'] ?? null) === 'system' && str_contains(($m['content'] ?? ''), 'Outils:')) {
				return $messages; // already injected
			}
		}
		array_unshift($messages, [ 'role' => 'system', 'content' => $instruction ]);
		return $messages;
	}

	protected function extractAssistantContent(array $response): ?string
	{
		// Attempt OpenAI-like shape first
		if (isset($response['choices'][0]['message']['content'])) {
			return $response['choices'][0]['message']['content'];
		}
		// Ollama typical shape: { message: { role: 'assistant', content: '...' } }
		if (isset($response['message']['content'])) {
			return $response['message']['content'];
		}
		return null;
	}

	/**
	 * Try to parse tool_calls JSON out of assistant content.
	 */
	protected function parseToolCalls(string $content): array
	{
		$result = [];
		$candidate = trim($content);
		// Extract fenced code block if present
		if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $candidate, $m)) {
			$candidate = trim($m[1]);
		}
		$decoded = json_decode($candidate, true);
		if (!is_array($decoded)) {
			// Fallback: extract first balanced JSON object (simple heuristic)
			if (preg_match('/\{[^{}]*\}/s', $candidate, $mm)) {
				$decoded = json_decode($mm[0], true);
			}
		}
		if (is_array($decoded)) {
			if (isset($decoded['tool_calls']) && is_array($decoded['tool_calls'])) {
				$result = $decoded['tool_calls'];
			} elseif (isset($decoded[0]) && is_array($decoded[0]) && array_key_exists('name', $decoded[0])) {
				$result = $decoded;
			}
		}
		return is_array($result) ? $result : [];
	}
}
