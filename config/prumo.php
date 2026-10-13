<?php

return [
    'brapi_token' => env('BRAPI_TOKEN'),

    'ai' => [
        'order' => array_filter(array_map('trim', explode(',', env('AI_PROVIDER_ORDER', 'gemini,groq,ollama,anthropic')))),
        'gemini' => ['key' => env('GEMINI_API_KEY'), 'model' => env('GEMINI_MODEL', 'gemini-flash-latest')],
        'groq' => ['key' => env('GROQ_API_KEY'), 'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile')],
        'ollama' => ['url' => env('OLLAMA_URL', 'http://ollama:11434'), 'model' => env('OLLAMA_MODEL', 'qwen2.5:3b')],
        'anthropic' => ['key' => env('ANTHROPIC_API_KEY'), 'model' => env('ANTHROPIC_MODEL', 'claude-opus-5-5')],
    ],

    'telegram' => [
        'token' => env('TELEGRAM_BOT_TOKEN'),
        'username' => env('TELEGRAM_BOT_USERNAME'),
    ],
];
