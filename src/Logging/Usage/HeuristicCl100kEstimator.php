<?php

namespace Iserter\UniformedAI\Logging\Usage;

use Iserter\UniformedAI\Services\Chat\DTOs\ChatRequest;

/**
 * Very rough heuristic approximating OpenAI cl100k tokenization.
 * Not exact; intentionally lightweight (no large BPE tables embedded).
 * Strategy: count words + punctuation clusters with weighting.
 */
class HeuristicCl100kEstimator implements TokenEstimator
{
    public function estimatePromptTokens(ChatRequest $request): int
    {
        $sum = 0;
        foreach ($request->messages as $m) {
            $sum += $this->count($m->content);
        }
        return $sum;
    }

    public function estimateCompletionTokens(string $completion): int
    { return $this->count($completion); }

    protected function count(string $text): int
    {
        if ($text === '') return 0;
        // Basic heuristic: tokens ~= words + punctuation + (chars / 4 for long words)
        $words = preg_split('/\s+/', trim($text));
        $wordCount = count(array_filter($words, fn($w) => $w !== ''));
        $punct = preg_match_all('/[\.,;:!?()\[\]{}]/u', $text, $m);
        $chars = mb_strlen($text);
        $est = (int) round($wordCount + $punct * 0.5 + $chars / 12);
        return max($est, 0);
    }
}
