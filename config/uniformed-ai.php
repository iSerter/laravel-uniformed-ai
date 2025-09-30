<?php

return [
    'defaults' => [
        'chat'   => env('AI_CHAT_DRIVER', 'openai'),
        'image'  => env('AI_IMAGE_DRIVER', 'openai'),
        'audio'  => env('AI_AUDIO_DRIVER', 'elevenlabs'),
        'music'  => env('AI_MUSIC_DRIVER', 'piapi'),
        'search' => env('AI_SEARCH_DRIVER', 'tavily'),
    ],

    'providers' => [
        'openai' => [
            'api_key'  => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'chat'  => ['model' => env('OPENAI_CHAT_MODEL', 'gpt-4.1-mini')],
            'image' => ['model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-1')],
        ],

        'openrouter' => [
            'api_key'  => env('OPENROUTER_API_KEY'),
            'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
            'chat' => ['model' => env('OPENROUTER_CHAT_MODEL', 'openrouter/auto')],
        ],

        'google' => [
            'api_key'  => env('GOOGLE_AI_API_KEY'),
            'base_url' => env('GOOGLE_AI_BASE_URL', 'https://generativelanguage.googleapis.com'),
            'chat' => ['model' => env('GOOGLE_CHAT_MODEL', 'gemini-1.5-pro')],
        ],

        'kie' => [
            'api_key' => env('KIE_AI_API_KEY'),
            'base_url' => env('KIE_AI_BASE_URL'),
        ],

        'piapi' => [
            'api_key' => env('PIAPI_API_KEY'),
            'base_url' => env('PIAPI_BASE_URL'),
            'music' => ['model' => env('PIAPI_MUSIC_MODEL', 'music/default')]
        ],

        'tavily' => [
            'api_key'  => env('TAVILY_API_KEY'),
            'base_url' => env('TAVILY_BASE_URL', 'https://api.tavily.com'),
            'search' => ['max_results' => 5]
        ],

        'elevenlabs' => [
            'api_key'  => env('ELEVENLABS_API_KEY'),
            'base_url' => env('ELEVENLABS_BASE_URL', 'https://api.elevenlabs.io'),
            'voice_id' => env('ELEVENLABS_VOICE_ID', 'Rachel'),
            'model'    => env('ELEVENLABS_MODEL', 'eleven_multilingual_v2'),
        ],
    ],

    'http' => [
        'timeout' => env('AI_HTTP_TIMEOUT', 60),
        'retries' => env('AI_HTTP_RETRIES', 2),
        'retry_delay_ms' => env('AI_HTTP_RETRY_DELAY_MS', 250),
    ],

    'cache' => [
        'store' => env('AI_CACHE_STORE', null),
        'ttl'   => env('AI_CACHE_TTL', 3600),
    ],

    'rate_limit' => [
        'openai'      => env('AI_RL_OPENAI', 0),
        'openrouter'  => env('AI_RL_OPENROUTER', 0),
        'google'      => env('AI_RL_GOOGLE', 0),
        'kie'         => env('AI_RL_KIE', 0),
        'piapi'       => env('AI_RL_PIAPI', 0),
        'tavily'      => env('AI_RL_TAVILY', 0),
        'elevenlabs'  => env('AI_RL_ELEVENLABS', 0),
    ],

    // Service Usage Logging (AI operation observability)
    'logging' => [
        'enabled' => env('SERVICE_USAGE_LOG_ENABLED', true),
        'connection' => env('SERVICE_USAGE_LOG_CONNECTION', null), // null => default DB connection
        'table' => env('SERVICE_USAGE_LOG_TABLE', 'service_usage_logs'),

        'queue' => [
            'enabled' => env('SERVICE_USAGE_LOG_QUEUE', false),
            'connection' => env('SERVICE_USAGE_LOG_QUEUE_CONNECTION', null),
            'queue' => env('SERVICE_USAGE_LOG_QUEUE_NAME', 'ai-usage-logs'),
        ],

        'truncate' => [
            'request_chars' => env('SERVICE_USAGE_LOG_TRUNCATE_REQUEST', 20000),
            'response_chars' => env('SERVICE_USAGE_LOG_TRUNCATE_RESPONSE', 40000),
            'chunk_chars' => env('SERVICE_USAGE_LOG_TRUNCATE_CHUNK', 2000),
        ],

        'stream' => [
            'store_chunks' => env('SERVICE_USAGE_LOG_STREAM_STORE_CHUNKS', true),
            'max_chunks' => env('SERVICE_USAGE_LOG_STREAM_MAX_CHUNKS', 500),
        ],

        'prune' => [
            'enabled' => env('SERVICE_USAGE_LOG_PRUNE_ENABLED', true),
            'days' => env('SERVICE_USAGE_LOG_PRUNE_DAYS', 30),
        ],

        'redaction' => [
            'mask' => env('SERVICE_USAGE_LOG_REDACTION_MASK', '***REDACTED***'),
        ],
    ],
];
