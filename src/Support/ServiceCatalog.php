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
            'openai' => ['gpt-5', 'gpt-5-mini', 'gpt-5-nano', 'gpt-5-pro', 'gpt-5.2', 'gpt-5.2-pro', 'gpt-4.1-mini','gpt-4.1','gpt-4o-mini','o3-mini'],
            'openrouter' => [
                'openai/gpt-5-pro', 'openai/gpt-5-mini', 'openai/gpt-5-codex', 'openai/gpt-5.2', 'openai/gpt-oss-120b', 'openai/gpt-4o', 'openai/gpt-4o-mini',
                'x-ai/grok-4-fast', 'x-ai/grok-4.1-fast', 'x-ai/grok-4', 'x-ai/grok-code-fast-1',
                'anthropic/claude-sonnet-4.5', 'anthropic/claude-opus-4.5', 'anthropic/claude-haiku-4.5', 'anthropic/claude-opus-4.1', 'anthropic/claude-opus-4', 'anthropic/claude-sonnet-4',
                'google/gemini-3-pro-preview', 'google/gemini-3-flash-preview', 'google/gemini-2.5-flash', 'google/gemini-2.5-pro',
                'deepseek/deepseek-v3.2',
                'meta-llama/llama-4-maverick', 'meta-llama/llama-4-scout',
                'qwen/qwen3-max', 'qwen/qwen3-235b-a22b', 'qwen/qwen3-235b-a22b-thinking-2507', 'qwen/qwen3-vl-235b-a22b-thinking',
            ],
            'replicate' => [
                'anthropic/claude-4.5-sonnet',
            ]
        ],
        'image' => [
            'openai' => ['gpt-image-1'],
            'kie' => ['mj', '4o'],
            'replicate' => ['google/nano-banana', 'stability-ai/stable-diffusion-3.5-large', 'black-forest-labs/flux-schnell']
        ],
        'audio' => [
            'openai' => ['tts-1', 'tts-1-hd', 'whisper-1'],
            'elevenlabs' => ['eleven_multilingual_v2'],
            'replicate' => ['minimax/speech-02-turbo','jaaari/kokoro-82m:f559560eb822dc509045f3921a1921234918b91739db4bf3daab2169b71c7a13']
        ],
        'music' => [
            'kie' => ['V3_5','V4','V4_5','V4_5PLUS'],
            'replicate' => ['google/lyria-2', 'minimax/music-1.5', 'meta/musicgen:671ac645ce5e552cc63a54a2bbff63fcf798043055d2dac5fc9e36a837eedcfb', 'riffusion/riffusion:8cf61ea6c56afd61d8f5b9ffd14d7c216c0a93844ce2d82ac1c9ecc9c7f24e05']
        ],
        'search' => [
            'tavily' => ['tavily/advanced','tavily/basic'],
        ],
        'video' => [
            // WARNING! these are quite expensive and some are not implemented yet
            'replicate' => ['minimax/hailuo-02', 'luma/ray', 'leonardoai/motion-2.0', 'bytedance/seedance-1-pro'],
            'kie' => [ 'veo3'],
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
