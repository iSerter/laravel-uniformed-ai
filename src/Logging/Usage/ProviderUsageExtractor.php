<?php

namespace Iserter\UniformedAI\Logging\Usage;

class ProviderUsageExtractor
{
    /**
     * Attempt to extract provider reported usage counts. Returns array with keys prompt, completion, total or empty.
     */
    public function extract(string $provider, ?array $raw): array
    {
        if (!$raw) return [];
        return match($provider) {
            'openai', 'openrouter' => $this->extractOpenAIStyle($raw),
            'google' => $this->extractGoogle($raw),
            default => [],
        };
    }

    protected function extractOpenAIStyle(array $raw): array
    {
        $u = $raw['usage'] ?? null; if (!is_array($u)) return [];
        $prompt = $u['prompt_tokens'] ?? null;
        $completion = $u['completion_tokens'] ?? null;
        $total = $u['total_tokens'] ?? (($prompt !== null && $completion !== null) ? $prompt + $completion : null);
        if ($prompt===null && $completion===null && $total===null) return [];
        return [ 'prompt' => $this->intOrNull($prompt), 'completion' => $this->intOrNull($completion), 'total' => $this->intOrNull($total) ];
    }

    protected function extractGoogle(array $raw): array
    {
        $meta = $raw['usageMetadata'] ?? null; if (!is_array($meta)) return [];
        $prompt = $meta['promptTokenCount'] ?? null;
        $completion = $meta['candidatesTokenCount'] ?? null;
        $total = $meta['totalTokenCount'] ?? (($prompt !== null && $completion !== null) ? $prompt + $completion : null);
        if ($prompt===null && $completion===null && $total===null) return [];
        return [ 'prompt' => $this->intOrNull($prompt), 'completion' => $this->intOrNull($completion), 'total' => $this->intOrNull($total) ];
    }

    protected function intOrNull($v): ?int { return is_numeric($v) ? (int) $v : null; }
}
