<?php

namespace Iserter\UniformedAI\Services\Chat\Contracts;

use Closure;
use Generator;
use Iserter\UniformedAI\Services\Chat\DTOs\ChatRequest;
use Iserter\UniformedAI\Services\Chat\DTOs\ChatResponse;

interface ChatContract
{
    public function send(ChatRequest $request): ChatResponse;

    /** Stream deltas. Callback receives partial string or structured delta array. */
    public function stream(ChatRequest $request, ?Closure $onDelta = null): Generator;
}
