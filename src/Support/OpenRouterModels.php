<?php

namespace Iserter\UniformedAI\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class OpenRouterModels
{
    /**
     * The OpenRouter API key.
     */
    protected string $apiKey;

    /**
     * The Laravel HTTP PendingRequest instance.
     */
    protected \Illuminate\Http\Client\PendingRequest $client;

    /**
     * Preferred exact model IDs for selection.
     */
    protected array $preferredExactModels = [
        'gemini-2.0-flash-exp',
        'deepseek-r1-zero',
    ];

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;

        // Build client using shared factory for consistency & retries.
        $this->client = HttpClientFactory::make([
            'api_key'  => $this->apiKey,
            'base_url' => 'https://openrouter.ai/api/v1/',
        ], 'openrouter');
    }

    public function getAvailableModels(): array
    {
        $response = $this->client->get('models');
        // Return 'data' or empty array if not present.
        return $response->json('data', []);
    }

    public function getFreeModels(bool $fresh = false): array
    {
        $cacheKey = 'openrouter_ai_free_models';
        if ($fresh || !Cache::has($cacheKey)) {
            $freeModels = [];
            foreach ($this->getAvailableModels() as $model) {
                if (isset($model['id']) && str_ends_with($model['id'], ':free')) {
                    $freeModels[] = $model;
                }
            }
            Cache::put($cacheKey, $freeModels, now()->addHours(6));
        } else {
            $freeModels = Cache::get($cacheKey, []);
        }
        return $freeModels;
    }

    /**
     * Set preferred exact models (fluent setter).
     *
     * @param array $models
     * @return $this
     */
    public function setPreferredExactModels(array $models): self
    {
        $this->preferredExactModels = $models;
        return $this;
    }

    public function getBestFreeModel(bool $getSmallest = false): ?array
    {
        $models = $this->getFreeModels();

        $preferredExactModels = $this->preferredExactModels;
        if ($getSmallest) {
            $preferredExactModels = array_merge($preferredExactModels, [
                'llama-3.3-70b-instruct',
                'llama-4-scout',
                'deepseek-chat',
            ]);
        }

        // if an exact model is available, choose it first.
        foreach ($preferredExactModels as $exactModel) {
            foreach ($models as $model) {
                if (str_contains($model['id'], $exactModel)) {
                    return $model;
                }
            }
        }

        // if no exact model is available, choose the best one based on size and preferred makers

        // Filter models based on preferred makers and input/output modalities
        $preferredMakers = ['openai', 'gemini', 'meta-llama', 'anthropic', 'deepseek', 'qwen', 'mistralai'];
        $models = array_filter($models, function ($model) use ($preferredMakers) {
            return
                in_array(explode('/', $model['id'])[0], $preferredMakers) &&
                in_array('text', $model['architecture']['input_modalities']) &&
                in_array('text', $model['architecture']['output_modalities']);
        });

        $minSize = 16; // Changed to numeric value
        $filteredBySize = [];
        foreach ($models as $model) {
            $nameWords = explode('-', substr($model['id'], 0, strpos($model['id'], ':')));
            $sizeWord = preg_grep('/\d+b/i', $nameWords); // Added case-insensitive flag
            if (count($sizeWord) > 0) {
                $sizeWord = array_values($sizeWord)[0];
                $sizeValue = (int) preg_replace('/[^0-9]/', '', $sizeWord); // Extract only numeric part
                if ($sizeValue < $minSize) {
                    continue;
                }
                $filteredBySize[] = array_merge($model, [
                    'size' => $sizeValue, // Store numeric size
                ]);
            }
        }

        usort($filteredBySize, function ($a, $b) use ($getSmallest) {
            // Sort ascending (smallest first) if $getSmallest is true
            // Sort descending (largest first) if $getSmallest is false
            if ($getSmallest) {
                return $a['size'] <=> $b['size'];
            } else {
                return $b['size'] <=> $a['size'];
            }
        });

        $bestModel = null;
        foreach ($preferredMakers as $maker) {
            foreach ($filteredBySize as $model) {
                if (str_starts_with($model['id'], $maker)) {
                    $bestModel = $model;
                    break 2;
                }
            }
        }

        if ($bestModel === null) {
            foreach ($preferredMakers as $maker) {
                foreach ($models as $model) {
                    if (str_starts_with($model['id'], $maker)) {
                        $bestModel = $model;
                        break 2;
                    }
                }
            }
        }

        return $bestModel;
    }
}