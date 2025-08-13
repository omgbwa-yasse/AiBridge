<?php

namespace AiBridge;

use AiBridge\Providers\OpenAIProvider;
use AiBridge\Providers\OllamaProvider;
use AiBridge\Providers\OllamaTurboProvider;
use AiBridge\Providers\OnnProvider;
use AiBridge\Providers\GeminiProvider;
use AiBridge\Providers\GrokProvider;
use AiBridge\Providers\ClaudeProvider;
use AiBridge\Providers\CustomOpenAIProvider;
use AiBridge\Contracts\ChatProviderContract;
use AiBridge\Contracts\EmbeddingsProviderContract;
use AiBridge\Contracts\ImageProviderContract;
use AiBridge\Contracts\AudioProviderContract;
use AiBridge\Contracts\ModelsProviderContract;
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
		if (!empty($config['ollama_turbo']['api_key'])) {
			$endpoint = $config['ollama_turbo']['endpoint'] ?? 'https://ollama.com';
			$this->providers['ollama_turbo'] = new OllamaTurboProvider($config['ollama_turbo']['api_key'], $endpoint);
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

		// OpenRouter (OpenAI-compatible schema at /api/v1)
		if (!empty($config['openrouter']['api_key'])) {
			$base = $config['openrouter']['base_url'] ?? 'https://openrouter.ai/api/v1';
			$headers = [];
			if (!empty($config['openrouter']['referer'])) { $headers['HTTP-Referer'] = $config['openrouter']['referer']; }
			if (!empty($config['openrouter']['title'])) { $headers['X-Title'] = $config['openrouter']['title']; }
			$this->providers['openrouter'] = new CustomOpenAIProvider(
				$config['openrouter']['api_key'],
				$base,
				[
					'chat' => '/chat/completions',
					'embeddings' => '/embeddings',
					'image' => '/images/generations',
					'tts' => '/audio/speech',
					'stt' => '/audio/transcriptions',
				],
				'Authorization', 'Bearer ', $headers
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
		$verify = $this->options['verify'] ?? null; // true|false|string (path)
		if ($retry > 0) {
			$pending = $pending->retry($retry, $sleep);
		}
		if ($timeout) {
			$pending = $pending->timeout($timeout);
		}
		if ($verify !== null) {
			$pending = $pending->withOptions(['verify' => $verify]);
		}
		return $pending;
	}

	public function provider(string $name): ?ChatProviderContract
	{
		$p = $this->providers[$name] ?? null;
		return $p instanceof ChatProviderContract ? $p : null;
	}

	/**
	 * Register or override a provider at runtime (useful for custom integrations and tests).
	 */
	public function registerProvider(string $name, $provider): self
	{
		$this->providers[$name] = $provider;
		return $this;
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

	/**
	 * Stream structured events if provider supports it; otherwise wrap plain stream chunks as delta events.
	 * Each event: ['type' => 'delta'|'end', 'data' => string|null]
	 */
	public function streamEvents(string $provider, array $messages, array $options = []): \Generator
	{
		$p = $this->providers[$provider] ?? null;
		if (!$p || !$p instanceof ChatProviderContract || !$p->supportsStreaming()) {
			throw ProviderException::unsupported($provider, 'streaming');
		}
		// If provider exposes streamEvents, use it directly
		if (method_exists($p, 'streamEvents')) {
			/** @var \Generator $gen */
			$gen = \call_user_func([$p, 'streamEvents'], $messages, $options);
			foreach ($gen as $evt) { yield $evt; }
			return;
		}
		// Fallback: wrap plain chunks as delta and emit a final end event
		foreach ($p->stream($messages, $options) as $chunk) {
			yield ['type' => 'delta', 'data' => $chunk];
		}
		yield ['type' => 'end', 'data' => null];
	}

	public function embeddings(string $provider, array $inputs, array $options = []): array
	{
		$p = $this->providers[$provider] ?? null;
		if (!$p || !$p instanceof EmbeddingsProviderContract) {
			throw ProviderException::unsupported($provider, 'embeddings');
		}
		return $p->embeddings($inputs, $options);
	}

	public function models(string $provider): array
	{
		$p = $this->providers[$provider] ?? null;
		if (!$p || !$p instanceof ModelsProviderContract) {
			throw ProviderException::unsupported($provider, 'models');
		}
		return $p->listModels();
	}

	public function model(string $provider, string $id): array
	{
		$p = $this->providers[$provider] ?? null;
		if (!$p || !$p instanceof ModelsProviderContract) {
			throw ProviderException::unsupported($provider, 'model');
		}
		return $p->getModel($id);
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
		$runner = new \AiBridge\Support\ToolChatRunner($this);
		return $runner->run($provider, $messages, $options);
	}
}
