<?php

return [
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],
    'ollama_turbo' => [
        'api_key' => env('OLLAMA_TURBO_API_KEY'),
        // Optional override, defaults to https://ollama.com
        'endpoint' => env('OLLAMA_TURBO_ENDPOINT', 'https://ollama.com'),
    ],
    'ollama' => [
        'endpoint' => env('OLLAMA_ENDPOINT', 'http://localhost:11434'),
    ],
    'onn' => [
        'api_key' => env('ONN_API_KEY'),
    ],
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
    ],
    'grok' => [
        'api_key' => env('GROK_API_KEY'),
    ],
    'claude' => [
        'api_key' => env('CLAUDE_API_KEY'),
    ],
    'openai_custom' => [
        'api_key' => env('OPENAI_CUSTOM_API_KEY'),
    // For Ollama OpenAI-compat, set to http://localhost:11434/v1 and use api_key="ollama"
    // e.g. OPENAI_CUSTOM_BASE_URL=http://localhost:11434/v1
    'base_url' => env('OPENAI_CUSTOM_BASE_URL'), // ex: https://my-proxy.example.com or http://localhost:11434/v1
        'paths' => [
            'chat' => env('OPENAI_CUSTOM_PATH_CHAT', '/v1/chat/completions'),
            'embeddings' => env('OPENAI_CUSTOM_PATH_EMBED', '/v1/embeddings'),
            'image' => env('OPENAI_CUSTOM_PATH_IMAGE', '/v1/images/generations'),
            'tts' => env('OPENAI_CUSTOM_PATH_TTS', '/v1/audio/speech'),
            'stt' => env('OPENAI_CUSTOM_PATH_STT', '/v1/audio/transcriptions'),
        ],
        // Pour Azure OpenAI par exemple: auth_header=api-key, auth_prefix=""
        'auth_header' => env('OPENAI_CUSTOM_AUTH_HEADER', 'Authorization'),
        'auth_prefix' => env('OPENAI_CUSTOM_AUTH_PREFIX', 'Bearer '),
        'extra_headers' => [
            // 'api-version' => '2024-02-15-preview'
        ],
    ],
    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        // Optional headers to surface your app in OpenRouter listings
        'referer' => env('OPENROUTER_REFERER'), // ex: https://your-app.example.com
        'title' => env('OPENROUTER_TITLE'),     // ex: Your App Name
    ],
    'options' => [
        'default_timeout' => env('LLM_HTTP_TIMEOUT', 30),
        'retry' => [ 'times' => env('LLM_HTTP_RETRY', 1), 'sleep' => env('LLM_HTTP_RETRY_SLEEP', 200) ],
    ],
    'security' => [
        'max_file_bytes' => env('LLM_MAX_FILE_BYTES', 2 * 1024 * 1024), // 2MB
        'allowed_mime_files' => [ 'text/plain', 'application/json', 'application/pdf' ],
        'allowed_mime_images' => [ 'image/png', 'image/jpeg', 'image/webp' ],
    ],
];
