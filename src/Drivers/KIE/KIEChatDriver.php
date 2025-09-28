<?php

namespace Iserter\UniformedAI\Drivers\KIE;

use Iserter\UniformedAI\Contracts\Chat\ChatContract;
use Iserter\UniformedAI\DTOs\{ChatRequest, ChatResponse};
use Iserter\UniformedAI\Support\HttpClientFactory;
use Iserter\UniformedAI\Exceptions\ProviderException;
use Iserter\UniformedAI\Support\RateLimiter;

class KIEChatDriver implements ChatContract
{
    public function __construct(private array $cfg, private ?RateLimiter $limiter = null) {}

    public function send(ChatRequest $request): ChatResponse
    {
        $this->limiter?->throttle('kie', (int) config('uniformed-ai.rate_limit.kie'));
        $http = HttpClientFactory::make($this->cfg);
        $payload = [
            'messages' => array_map(fn($m) => ['role'=>$m->role,'content'=>$m->content], $request->messages),
        ];
        $res = $http->post('chat', $payload);
        if (!$res->successful()) throw new ProviderException('KIE error', 'kie', $res->status(), $res->json());
        return new ChatResponse($res->json('reply') ?? '', null, null, $res->json());
    }

    public function stream(ChatRequest $request, ?\Closure $onDelta = null): \Generator
    {
        yield from (function(){ if (false) yield ''; })();
    }
}
