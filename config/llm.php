<?php

return [
    'default_provider' => env('LLM_DEFAULT_PROVIDER', 'openai'),
    'rate_limit_per_minute' => (int) env('LLM_RATE_LIMIT_PER_MINUTE', 30),
    'max_input_chars' => (int) env('LLM_MAX_INPUT_CHARS', 12000),
    'max_output_chars' => (int) env('LLM_MAX_OUTPUT_CHARS', 24000),
    'mask_secret_patterns' => [
        '/sk-[A-Za-z0-9]{20,}/',
        '/AKIA[0-9A-Z]{16}/',
        '/-----BEGIN [A-Z ]+ PRIVATE KEY-----/',
    ],
    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'model' => env('OPENAI_MODEL', 'gpt-4.1-mini'),
            'timeout' => (int) env('OPENAI_TIMEOUT', 30),
        ],
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
            'model' => env('ANTHROPIC_MODEL', 'claude-3-5-sonnet-latest'),
            'timeout' => (int) env('ANTHROPIC_TIMEOUT', 30),
            'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
        ],
    ],
];
