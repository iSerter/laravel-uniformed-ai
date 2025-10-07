<?php

namespace Iserter\UniformedAI\Support;

/**
 * Central curated catalog of supported providers and model identifiers per service.
 * Phase 1: Static hardcoded arrays (zero network). Future phases may merge config overrides
 * or dynamic discovery. Treat model names as opaque identifiers suitable for passing to
 * provider drivers.
 */
class ServiceCatalog
{
    /**
     * Hierarchical map: service => provider => models[]
     */
    public const MAP = [
        'chat' => [
            'openai' => ['gpt-4.1-mini','gpt-4.1','gpt-4o-mini','o3-mini'],
            'openrouter' => ['openrouter/auto','anthropic/claude-3.5-sonnet','meta/llama-3.1-70b-instruct'],
            'google' => ['gemini-1.5-pro','gemini-1.5-flash','gemini-exp-1206'],
            'kie' => ['kie/chat-standard'],
            'piapi' => ['piapi/chat-general'],
        ],
        'image' => [
            'openai' => ['gpt-image-1'],
        ],
        'audio' => [
            'elevenlabs' => ['eleven_multilingual_v2'],
        ],
        'music' => [
            'piapi' => ['music/default','music/v2-beta'],
        ],
        'search' => [
            'tavily' => ['tavily/advanced','tavily/basic'],
        ],
    ];

    /**
     * Safely get providers for a service key.
     * @return string[]
     */
    public static function providers(string $service): array
    {
        return array_keys(self::MAP[$service] ?? []);
    }

    /**
     * Safely get models for a service + provider pair.
     * @return string[]
     */
    public static function models(string $service, string $provider): array
    {
        return self::MAP[$service][$provider] ?? [];
    }
}
