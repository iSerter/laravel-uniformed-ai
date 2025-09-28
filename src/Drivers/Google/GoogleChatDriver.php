<?php

namespace Iserter\UniformedAI\Drivers\Google;

use Closure; use Generator;
use Iserter\UniformedAI\Contracts\Chat\ChatContract;
use Iserter\UniformedAI\DTOs\{ChatRequest, ChatResponse};
use Iserter\UniformedAI\Support\HttpClientFactory;
use Iserter\UniformedAI\Exceptions\ProviderException;
use Iserter\UniformedAI\Support\RateLimiter;

class GoogleChatDriver implements ChatContract
{
    public function __construct(private array $cfg, private ?RateLimiter $limiter = null) {}

    public function send(ChatRequest $request): ChatResponse
    {
        $this->limiter?->throttle('google', (int) config('uniformed-ai.rate_limit.google'));
        $http = HttpClientFactory::make($this->cfg);
        $model = $request->model ?? ($this->cfg['chat']['model'] ?? 'gemini-1.5-pro');
        $contents = array_map(fn($m) => [
            'role' => $m->role,
            'parts' => [['text' => $m->content]],
        ], $request->messages);

        $res = $http->post("v1beta/models/{$model}:generateContent?key=".$this->cfg['api_key'], [
            'contents' => $contents,
        ]);
        if (!$res->successful()) throw new ProviderException('Google AI error', 'google', $res->status(), $res->json());

        $text = $res->json('candidates.0.content.parts.0.text') ?? '';
        return new ChatResponse($text, null, $model, $res->json());
    }

    public function stream(ChatRequest $request, ?Closure $onDelta = null): Generator
    {
        // Streaming omitted
        yield from (function(){ if (false) yield ''; })();
    }
}
