<?php

namespace Iserter\UniformedAI\Drivers\PIAPI;

use Iserter\UniformedAI\Contracts\Chat\ChatContract;
use Iserter\UniformedAI\DTOs\{ChatRequest, ChatResponse};
use Iserter\UniformedAI\Support\HttpClientFactory;
use Iserter\UniformedAI\Exceptions\ProviderException;
use Iserter\UniformedAI\Support\RateLimiter;

class PIAPIChatDriver implements ChatContract
{
    public function __construct(private array $cfg, private ?RateLimiter $limiter = null) {}

    public function send(ChatRequest $request): ChatResponse
    {
        $this->limiter?->throttle('piapi', (int) config('uniformed-ai.rate_limit.piapi'));
        $http = HttpClientFactory::make($this->cfg);
        $payload = [
            'messages' => array_map(fn($m) => ['role'=>$m->role,'content'=>$m->content], $request->messages),
        ];
        $res = $http->post('chat', $payload);
        if (!$res->successful()) throw new ProviderException('PIAPI error', 'piapi', $res->status(), $res->json());
        return new ChatResponse($res->json('reply') ?? '', null, null, $res->json());
    }

    public function stream(ChatRequest $request, ?\Closure $onDelta = null): \Generator
    {
        yield from (function(){ if (false) yield ''; })();
    }
}
