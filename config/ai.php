<?php

return [
    'provider' => env('AI_PROVIDER', 'ollama'),

    'providers' => [
        'ollama' => [
            'base_url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),
            'model' => env('OLLAMA_MODEL', 'qwen3:8b'),
            'timeout' => env('OLLAMA_TIMEOUT', 60),
            'connect_timeout' => env('OLLAMA_CONNECT_TIMEOUT', 5),
            'response_timeout' => env('OLLAMA_RESPONSE_TIMEOUT', 90),
        ],
    ],
];
