<?php

namespace Iserter\UniformedAI\Services\Chat\Providers;

use Iserter\UniformedAI\Services\Chat\Contracts\ChatContract;
use Iserter\UniformedAI\Services\Chat\DTOs\{ChatRequest, ChatResponse};

class PIAPIChatDriver implements ChatContract
{
    public function __construct(private array $cfg) {}

    public function send(ChatRequest $request): ChatResponse
    {
        return new ChatResponse('[PIAPI placeholder response]', null, $request->model, ['placeholder' => true]);
    }

    public function stream(ChatRequest $request, ?\Closure $onDelta = null): \Generator
    {
        yield from (function(){ if (false) yield ''; })();
    }
}
