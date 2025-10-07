<?php

namespace Iserter\UniformedAI\Support;

/**
 * Central curated catalog of supported providers and model identifiers per service.
 * Phase 1: Static hardcoded arrays (zero network). Future phases may merge config overrides
 * or dynamic discovery. Treat model names as opaque identifiers suitable for passing to
 * provider drivers. This is not a source of truth for capabilities or pricing.
 * IT DOES NOT CONSTRAIN WHAT CAN BE PASSED TO DRIVERS.
 */
class ServiceCatalog
{
    /**
     * Hierarchical map: service => provider => models[]
     */
    public const MAP = [
        'chat' => [
            'openai' => ['gpt-5', 'gpt-5-mini', 'gpt-5-nano', 'gpt-5-pro', 'gpt-4.1-mini','gpt-4.1','gpt-4o-mini','o3-mini'],
            'openrouter' => [
                'openrouter/auto',
                'google/gemini-2.5-flash', 'google/gemini-2.5-pro', 
                'qwen/qwen3-max', 'qwen/qwen3-235b-a22b', 'qwen/qwen3-vl-235b-a22b-thinking', 
                'openai/gpt-5-pro', 'openai/gpt-5-mini', 'openai/gpt-5-codex', 'openai/gpt-4o', 'openai/gpt-4o-mini',
                'x-ai/grok-4-fast', 'x-ai/grok-4', 'x-ai/grok-code-fast-1',
                'anthropic/claude-sonnet-4.5', 'anthropic/claude-opus-4.1', 'anthropic/claude-opus-4', 'anthropic/claude-sonnet-4',
            ],
        ],
        'image' => [
            'openai' => ['gpt-image-1'],
            'kie' => ['mj', '4o']
        ],
        'audio' => [
            'elevenlabs' => ['eleven_multilingual_v2'],
        ],
        'music' => [
            'kie' => ['V3_5'],
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
