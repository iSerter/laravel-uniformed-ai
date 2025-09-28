<?php

namespace Iserter\UniformedAI\Drivers\OpenRouter;

use Iserter\UniformedAI\Contracts\Chat\ChatContract;
use Iserter\UniformedAI\DTOs\{ChatRequest, ChatResponse};
use Iserter\UniformedAI\Support\HttpClientFactory;
use Iserter\UniformedAI\Exceptions\ProviderException;
use Iserter\UniformedAI\Support\RateLimiter;

class OpenRouterChatDriver implements ChatContract
{
    public function __construct(private array $cfg, private ?RateLimiter $limiter = null) {}

    public function send(ChatRequest $request): ChatResponse
    {
        $this->limiter?->throttle('openrouter', (int) config('uniformed-ai.rate_limit.openrouter'));
        $http = HttpClientFactory::make($this->cfg);
        $model = $request->model ?? ($this->cfg['chat']['model'] ?? 'openrouter/auto');
        $payload = [
            'model' => $model,
            'messages' => array_map(fn($m) => ['role'=>$m->role,'content'=>$m->content], $request->messages),
        ];
        $res = $http->post('chat/completions', $payload);
        if (!$res->successful()) throw new ProviderException('OpenRouter error', 'openrouter', $res->status(), $res->json());
        return new ChatResponse($res->json('choices.0.message.content') ?? '', null, $model, $res->json());
    }

    public function stream(ChatRequest $request, ?\Closure $onDelta = null): \Generator
    {
        // SSE stub
        yield from (function(){ if (false) yield ''; })();
    }
}
