<?php

namespace Iserter\UniformedAI\Managers;

use Closure;
use Generator;
use Illuminate\Support\Manager;
use Iserter\UniformedAI\Contracts\Chat\ChatContract;
use Iserter\UniformedAI\DTOs\ChatRequest;
use Iserter\UniformedAI\DTOs\ChatResponse;
use Iserter\UniformedAI\Drivers\OpenAI\OpenAIChatDriver;
use Iserter\UniformedAI\Support\RateLimiter;
use Iserter\UniformedAI\Drivers\OpenRouter\OpenRouterChatDriver;
use Iserter\UniformedAI\Drivers\Google\GoogleChatDriver;
use Iserter\UniformedAI\Drivers\KIE\KIEChatDriver;
use Iserter\UniformedAI\Drivers\PIAPI\PIAPIChatDriver;

class ChatManager extends Manager implements ChatContract
{
    public function getDefaultDriver()
    {
        return config('uniformed-ai.defaults.chat');
    }

    // Uniform API (proxy to underlying driver)
    public function send(ChatRequest $request): ChatResponse
    {
        return $this->driver()->send($request);
    }

    public function stream(ChatRequest $request, ?Closure $onDelta = null): Generator
    {
        return $this->driver()->stream($request, $onDelta);
    }

    protected function createOpenaiDriver(): ChatContract
    {
        $cfg = config('uniformed-ai.providers.openai');
        if (empty($cfg['base_url'])) { $cfg['base_url'] = 'https://api.openai.com/v1'; }
        return new OpenAIChatDriver($cfg, app(RateLimiter::class));
    }

    protected function createOpenrouterDriver(): ChatContract
    {
        return new OpenRouterChatDriver(config('uniformed-ai.providers.openrouter'));
    }

    protected function createGoogleDriver(): ChatContract
    {
        return new GoogleChatDriver(config('uniformed-ai.providers.google'));
    }

    protected function createKieDriver(): ChatContract
    {
        return new KIEChatDriver(config('uniformed-ai.providers.kie'));
    }

    protected function createPiapiDriver(): ChatContract
    {
        return new PIAPIChatDriver(config('uniformed-ai.providers.piapi'));
    }
}
