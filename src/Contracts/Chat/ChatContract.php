<?php

namespace Iserter\UniformedAI\Contracts\Chat;

use Closure;
use Generator;
use Iserter\UniformedAI\DTOs\{ChatRequest, ChatResponse};

interface ChatContract
{
    public function send(ChatRequest $request): ChatResponse;

    /** Stream deltas. Callback receives partial string or structured delta array. */
    public function stream(ChatRequest $request, ?Closure $onDelta = null): Generator;
}
