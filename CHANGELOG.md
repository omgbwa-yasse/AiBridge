# Changelog

All notable changes to this project will be documented in this file.

## v2.5.0 (2025-08-18)

Added

- Fluent TextBuilder API for simpler, chainable text generation: `AiBridge::text()` with `using()`, `withPrompt()`, `withSystemPrompt()`, `withMaxTokens()`, `usingTemperature()`, `usingTopP()`, and outputs via `asText()`, `asRaw()`, `asStream()`.
- Structured streaming via `AiBridge\Support\StreamChunk`: `asStream()` now yields normalized chunks with `text`, `usage`, `finishReason`, `chunkType`, `toolCalls`, and `toolResults` when available.
- Documentation updates (EN/FR): new sections “Fluent text builder” and “Streaming Output (builder)” with Laravel SSE/Event Streams examples and streaming caveats.
- Examples: `examples/builder_ollama_smoke.php` and `examples/builder_stream_ollama.php` showcase builder chat and streaming.

Changed

- Stream builder wraps provider deltas into `StreamChunk` for consistent handling across providers.
- Notes on HTTP client interceptors (e.g., Telescope) that can consume streams.

Fixed

- Minor README cleanups and typos.

## v2.0.0 (2025-08-18)

Added

- Per-call provider overrides: you can now pass `api_key`, `endpoint`, `base_url`, or `chat_endpoint` (and for custom OpenAI: `paths`, `auth_header`, `auth_prefix`, `extra_headers`) directly in the `options` parameter of each call.
  - Applies to: `chat`, `stream`, `streamEvents`, `embeddings`, `image`, `tts`, `stt`.
  - Example: `AiBridge::chat('ollama', $messages, ['endpoint' => 'http://myhost:11434', 'model' => 'phi3'])`
  - Example: `AiBridge::chat('openai', $messages, ['api_key' => 'sk-...', 'chat_endpoint' => 'https://api.openai.com/v1/chat/completions'])`
  - Example: `AiBridge::chat('openai_custom', $messages, ['api_key' => 'abc', 'base_url' => 'http://localhost:11434/v1', 'paths' => ['chat' => '/chat/completions']])`
- Auto-registration: if a provider isn’t pre-configured, passing sufficient overrides will bootstrap it for subsequent calls in the same process.

Changed

- Manager now resolves providers per call, honoring overrides first, then falling back to configured instances.

Notes

- Model discovery (`models()/model()`) still requires pre-configured providers.
- Maintaining backward compatibility: existing env/config-based flows continue to work unchanged.

## v1.0.0 (Initial release)

Features

- Unified interface for multiple AI providers: OpenAI, Ollama, Ollama Turbo, Gemini, Grok, Claude, ONN, and custom OpenAI-compatible endpoints (including OpenRouter/Azure/proxies).
- Chat, streaming, embeddings, image generation, TTS/STT (where supported by provider).
- Tooling support: function/tool calls normalization and a tool runner for provider-agnostic loops.
- Normalizers for images, audio, and embeddings to harmonize outputs across providers.
- Security helpers for file handling and simple validations.
- Configurable HTTP retry/timeout and TLS verification pass-through.
