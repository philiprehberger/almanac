<?php

return [
    'default_chat_provider' => env('ALMANAC_DEFAULT_CHAT_PROVIDER', 'mock'),
    'default_embed_provider' => env('ALMANAC_DEFAULT_EMBED_PROVIDER', 'mock'),

    'openai_api_key' => env('OPENAI_API_KEY'),
    'openai_chat_model' => env('OPENAI_CHAT_MODEL', 'gpt-4o-mini'),
    'openai_embed_model' => env('OPENAI_EMBED_MODEL', 'text-embedding-3-small'),

    'anthropic_api_key' => env('ANTHROPIC_API_KEY'),
    'anthropic_chat_model' => env('ANTHROPIC_CHAT_MODEL', 'claude-sonnet-4-6'),

    'ollama_base_url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),
    'ollama_chat_model' => env('OLLAMA_CHAT_MODEL', 'llama3'),

    'embed_worker_url' => env('ALMANAC_EMBED_WORKER_URL', 'http://127.0.0.1:8001'),
    'embed_worker_shared_secret' => env('ALMANAC_EMBED_WORKER_SHARED_SECRET'),

    'connectors_mock_mode' => filter_var(env('ALMANAC_CONNECTORS_MOCK_MODE', true), FILTER_VALIDATE_BOOLEAN),

    'demo' => [
        'workspace_slug' => env('ALMANAC_DEMO_WORKSPACE_SLUG', 'demo'),
        'monthly_budget_usd' => (float) env('ALMANAC_DEMO_MONTHLY_BUDGET_USD', 25),
        'rate_limit_per_hour' => (int) env('ALMANAC_DEMO_RATE_LIMIT_PER_HOUR', 30),
    ],
];
