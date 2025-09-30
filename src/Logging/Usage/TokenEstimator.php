<?php

namespace Iserter\UniformedAI\Logging\Usage;

use Iserter\UniformedAI\Services\Chat\DTOs\ChatRequest;

interface TokenEstimator
{
    public function estimatePromptTokens(ChatRequest $request): int;
    public function estimateCompletionTokens(string $completion): int;
}
