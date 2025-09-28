<?php

namespace Iserter\UniformedAI\Services\Chat;

use Illuminate\Support\Manager;
use Iserter\UniformedAI\Services\Chat\Contracts\ChatContract;
use Iserter\UniformedAI\Services\Chat\Providers\{OpenAIChatDriver, OpenRouterChatDriver, GoogleChatDriver, KIEChatDriver, PIAPIChatDriver};
use Iserter\UniformedAI\Support\RateLimiter;
use Iserter\UniformedAI\Support\Concerns\SupportsUsing;

class ChatManager extends Manager implements ChatContract
{
    use SupportsUsing;
    public function getDefaultDriver()
    {
        return config('uniformed-ai.defaults.chat');
    }

    protected function applyRateLimit(string $provider): void
    {
        $limit = (int) config("uniformed-ai.rate_limit.{$provider}", 0);
        if ($limit > 0) {
            app(RateLimiter::class)->throttle($provider, $limit);
        }
    }

    // Uniform API with rate limiting
    public function send(\Iserter\UniformedAI\Services\Chat\DTOs\ChatRequest $request): \Iserter\UniformedAI\Services\Chat\DTOs\ChatResponse
    {
        $provider = $this->getDefaultDriver();
        $this->applyRateLimit($provider);
        return $this->driver($provider)->send($request);
    }

    public function stream(\Iserter\UniformedAI\Services\Chat\DTOs\ChatRequest $request, $onDelta = null): \Generator
    {
        $provider = $this->getDefaultDriver();
        $this->applyRateLimit($provider);
        return $this->driver($provider)->stream($request, $onDelta);
    }

    // Drivers
    protected function createOpenaiDriver(): ChatContract
    {
        return new OpenAIChatDriver(config('uniformed-ai.providers.openai'));
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
