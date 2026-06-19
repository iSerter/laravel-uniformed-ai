<?php

namespace Iserter\UniformedAI\Integration\Concerns;

trait ExtractsUsage
{
    /**
     * Extract token usage from an unknown response object via duck typing.
     *
     * Tries multiple common shapes without importing any external types:
     * 1. $response->usage->promptTokens / completionTokens (laravel/ai style)
     * 2. $response->usage->inputTokens / outputTokens (Anthropic style)
     * 3. $response->usage as array with prompt_tokens / completion_tokens keys
     * 4. $response->usage() method
     *
     * @return array{prompt_tokens: int, completion_tokens: int}|null
     */
    protected function extractUsageFromResponse(mixed $response): ?array
    {
        if (!is_object($response)) {
            return null;
        }

        $usage = $this->resolveUsageValue($response);

        if ($usage === null) {
            return null;
        }

        if (is_object($usage)) {
            return $this->extractFromUsageObject($usage);
        }

        if (is_array($usage)) {
            return $this->extractFromUsageArray($usage);
        }

        return null;
    }

    /**
     * Extract a loggable response payload from an unknown response object.
     */
    protected function extractResponsePayload(mixed $response): mixed
    {
        if (!is_object($response)) {
            return $response;
        }

        if (property_exists($response, 'text')) {
            return ['text' => $response->text];
        }

        if (method_exists($response, 'toArray')) {
            return $response->toArray();
        }

        return $response;
    }

    /**
     * Resolve the raw usage value from a response object.
     */
    private function resolveUsageValue(object $response): mixed
    {
        if (property_exists($response, 'usage')) {
            return $response->usage;
        }

        if (method_exists($response, 'usage')) {
            return $response->usage();
        }

        return null;
    }

    /**
     * Extract tokens from a usage object with named properties.
     */
    private function extractFromUsageObject(object $usage): ?array
    {
        // Shape: promptTokens / completionTokens
        if (property_exists($usage, 'promptTokens') && property_exists($usage, 'completionTokens')) {
            return [
                'prompt_tokens' => (int) $usage->promptTokens,
                'completion_tokens' => (int) $usage->completionTokens,
            ];
        }

        // Shape: inputTokens / outputTokens
        if (property_exists($usage, 'inputTokens') && property_exists($usage, 'outputTokens')) {
            return [
                'prompt_tokens' => (int) $usage->inputTokens,
                'completion_tokens' => (int) $usage->outputTokens,
            ];
        }

        return null;
    }

    /**
     * Extract tokens from a usage array.
     */
    private function extractFromUsageArray(array $usage): array
    {
        return [
            'prompt_tokens' => (int) ($usage['prompt_tokens'] ?? $usage['promptTokens'] ?? $usage['input_tokens'] ?? 0),
            'completion_tokens' => (int) ($usage['completion_tokens'] ?? $usage['completionTokens'] ?? $usage['output_tokens'] ?? 0),
        ];
    }
}
