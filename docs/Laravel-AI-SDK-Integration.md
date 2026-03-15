# Complementing Laravel AI SDK with iserter/laravel-uniformed-ai

Laravel's official `laravel/ai` package provides a unified API for agents, images, audio, embeddings, and more. This document outlines how **iserter/laravel-uniformed-ai** complements (not replaces) `laravel/ai` by adding **usage logging**, **cost calculation**, and **video generation**.

> **Important:** This package has **zero dependency** on `laravel/ai`. All integration code is provider-agnostic — it works with laravel/ai, direct API calls, or any other AI client.

**References:** [Laravel AI SDK docs](https://laravel.com/docs/12.x/ai-sdk), [laravel/ai on GitHub](https://github.com/laravel/ai).

---

## 1. Why complement instead of compete?

| Area | laravel/ai | laravel-uniformed-ai (this package) |
|------|------------|-------------------------------------|
| **Agents / Chat** | First-class (agents, tools, streaming, middleware) | Uniform chat API across OpenAI, Anthropic, Google, OpenRouter |
| **Usage logging** | Not built-in | **Built-in:** `service_usage_logs` with request/response/latency, optional queue |
| **Cost calculation** | Not built-in | **Built-in:** token-based pricing with DB-driven rates and tiered pricing |
| **Video generation** | Not supported | **Supported:** Kling, Google, ElevenLabs, KIE, Replicate |
| **Pricing data** | N/A | DB-driven (`service_pricings` + tiers), bundled pricing migrations |

**Use laravel/ai for agents and orchestration; use this package for observability (logging + cost) and video.**

---

## 2. Integration approach

The integration is **generic and provider-agnostic**. This package never imports, references, or depends on any `laravel/ai` class. Instead, it exposes a simple logging and pricing API that any caller — including laravel/ai users — can use.

Five integration levels (from simplest to most automated):

### Level 1: Direct API (zero setup)

Users call `LogDraft` and `PricingEngine` directly after any AI call:

```php
use Iserter\UniformedAI\Logging\LogDraft;
use Iserter\UniformedAI\Logging\Usage\PricingEngine;

// After a laravel/ai agent call (or any AI call):
$draft = LogDraft::start('chat', 'anthropic', 'prompt', [
    'messages' => $messages,
], 'claude-sonnet-4-5-20250514');

try {
    $response = $agent->prompt($input); // laravel/ai call

    // Extract usage from the response (shape depends on the AI client)
    $usage = $response->usage; // or however the client exposes it

    $pricing = app(PricingEngine::class)->price(
        'anthropic', 'claude-sonnet-4-5-20250514', 'chat',
        $usage['prompt_tokens'], $usage['completion_tokens']
    );

    $draft->attachUsageMetrics([
        'prompt_tokens'    => $usage['prompt_tokens'],
        'completion_tokens' => $usage['completion_tokens'],
        'total_tokens'     => $usage['total_tokens'],
        'confidence'       => 'reported',
        ...$pricing,
    ]);

    $draft->finishSuccess(['text' => $response->text]);
} catch (\Throwable $e) {
    $draft->finishError($e);
    throw $e;
} finally {
    $draft->persist();
}
```

This works today with no changes to the package.

### Level 2: Helper service (convenience wrapper)

A thin service class that wraps the Level 1 pattern into a reusable callback:

```php
use Iserter\UniformedAI\Integration\UsageLogger;

$result = app(UsageLogger::class)->record(
    provider: 'openai',
    model: 'gpt-4.1',
    serviceType: 'chat',
    operation: 'prompt',
    request: ['messages' => $messages],
    execute: function () use ($agent, $input) {
        return $agent->prompt($input);
    },
    extractUsage: function ($response) {
        // User-defined: extract tokens from whatever response shape they have
        return [
            'prompt_tokens' => $response->usage->promptTokens,
            'completion_tokens' => $response->usage->completionTokens,
        ];
    },
);
```

The `extractUsage` callback keeps this package decoupled — users map their own response shape to our expected token array. The package never needs to know about `AgentResponse` or any external type.

### Level 3: Laravel event listener (automatic)

For laravel/ai users who want automatic logging without touching each call site, a generic event listener approach:

```php
// In a service provider or event subscriber:
use Laravel\Ai\Events\AgentCompleted; // laravel/ai event (in user's code, not ours)
use Iserter\UniformedAI\Integration\UsageLogger;

Event::listen(AgentCompleted::class, function ($event) {
    app(UsageLogger::class)->record(
        provider: $event->provider,
        model: $event->model,
        serviceType: 'chat',
        operation: 'prompt',
        request: $event->input,
        execute: fn () => $event->response, // already resolved
        extractUsage: fn ($r) => [
            'prompt_tokens' => $r->usage->promptTokens,
            'completion_tokens' => $r->usage->completionTokens,
        ],
    );
});
```

The listener lives in **the user's application**, not in this package. We only provide the `UsageLogger` service — the user writes the glue code that references laravel/ai types.

### Level 4: TracksUsage trait (drop-in for Agent classes)

Add the `TracksUsage` trait to your Agent class. It provides `trackedPrompt()` and `trackedStream()` methods that automatically log usage and calculate cost. Usage extraction uses duck typing — it detects token data from any response shape without importing laravel/ai types.

```php
use Iserter\UniformedAI\Integration\Concerns\TracksUsage;

class SalesCoach implements Agent
{
    use Promptable, TracksUsage;

    public function instructions(): string
    {
        return 'You are a sales coach...';
    }

    // Override to set provider/model for pricing lookup
    protected function usageProvider(): string { return 'anthropic'; }
    protected function usageModel(): string { return 'claude-sonnet-4-5-20250514'; }
}

// In your controller or service:
$agent = new SalesCoach;
$response = $agent->trackedPrompt(
    'Improve my pitch for enterprise clients',
    fn () => $agent->prompt('Improve my pitch for enterprise clients'),
);
// $response is the original AgentResponse — usage is logged automatically

// Streaming:
foreach ($agent->trackedStream(
    'Analyze this transcript',
    fn () => $agent->stream('Analyze this transcript'),
) as $delta) {
    echo $delta;
}
```

The trait auto-detects usage from common response shapes:
- `$response->usage->promptTokens` / `completionTokens` (laravel/ai)
- `$response->usage->inputTokens` / `outputTokens` (Anthropic)
- `$response->usage` as array with `prompt_tokens` / `completion_tokens`
- `$response->usage()` method returning any of the above

You can override `extractUsageFromResponse()` for custom shapes.

### Level 5: RecordUsageMiddleware (transparent, zero call-site changes)

For fully automatic logging without changing any call sites, use the middleware with laravel/ai's `HasMiddleware` interface:

```php
use Iserter\UniformedAI\Integration\RecordUsageMiddleware;

class SalesCoach implements Agent, HasMiddleware
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are a sales coach...';
    }

    public function middleware(): array
    {
        return [
            // Auto-detect provider/model from the prompt:
            RecordUsageMiddleware::make(),

            // Or set them explicitly:
            RecordUsageMiddleware::make(
                provider: 'anthropic',
                model: 'claude-sonnet-4-5-20250514',
            ),
        ];
    }
}

// Every prompt() call is now automatically logged — no changes needed:
$response = (new SalesCoach)->prompt(
    'Improve my pitch',
    provider: Lab::Anthropic,
    model: 'claude-sonnet-4-5-20250514',
);
```

The middleware duck-types provider/model from the prompt object (including `Lab` enum values). For custom usage extraction, pass a closure:

```php
RecordUsageMiddleware::make(
    extractUsage: fn ($response) => [
        'prompt_tokens' => $response->usage->promptTokens,
        'completion_tokens' => $response->usage->completionTokens,
    ],
)
```

> **Note:** The middleware handles synchronous `prompt()` calls. For streaming, use the `TracksUsage` trait's `trackedStream()` method or the `UsageLogger` service directly.

**Trait vs Middleware — when to use which:**

| | TracksUsage trait | RecordUsageMiddleware |
|---|---|---|
| **Call-site changes** | Yes (`trackedPrompt()` instead of `prompt()`) | None |
| **Streaming** | Supported via `trackedStream()` | Use trait or `UsageLogger` directly |
| **Per-call override** | Provider/model can be overridden per call | Set once in middleware config |
| **Best for** | Selective logging of specific calls | Logging every call on an agent |

---

## 3. Components

### 3.1 `UsageLogger` (service)

A convenience wrapper around `LogDraft` + `PricingEngine`:

- **Input:** provider, model, service type, operation, request payload, a callable to execute, and a callable to extract usage.
- **Output:** whatever the execute callable returns.
- **Side effect:** creates and persists a `ServiceUsageLog` entry with usage metrics and cost.
- **Streaming:** `recordStream()` wraps an iterable, accumulates chunks, and persists on completion.

### 3.2 `TracksUsage` (trait)

Added to Agent classes. Provides `trackedPrompt()` and `trackedStream()` wrapper methods that delegate to `UsageLogger`. Configurable via overridable methods: `usageProvider()`, `usageModel()`, `usageServiceType()`.

### 3.3 `RecordUsageMiddleware` (middleware)

Compatible with laravel/ai's `HasMiddleware` pattern. Constructor accepts optional provider, model, serviceType, and extractUsage callable. Duck-types prompt object properties (including enum-style providers). Static `make()` factory for fluent construction.

### 3.4 `ExtractsUsage` (internal trait)

Shared duck-typing logic used by both `TracksUsage` and `RecordUsageMiddleware`. Extracts token usage from unknown response objects by trying multiple property shapes. Not intended for direct use.

### 3.5 Provider/model mapping (config-based)

If laravel/ai uses different provider identifiers than our `service_pricings` table, users can add a mapping in config:

```php
// config/uniformed-ai.php
'provider_aliases' => [
    'open-ai' => 'openai',      // if laravel/ai uses 'open-ai'
    'Anthropic' => 'anthropic',  // if Lab enum value is capitalized
],
```

---

## 4. File / namespace layout

```
src/
  Integration/
    Concerns/
      ExtractsUsage.php          # shared duck-typing logic (internal)
      TracksUsage.php            # trait for Agent classes
    RecordUsageMiddleware.php    # agent middleware
    UsageLogger.php              # convenience wrapper service
  Logging/                       # existing (LogDraft, decorators, usage)
  Support/                       # existing (PricingRepository)
  ...
```

No `LaravelAi/` subdirectory. No imports of `laravel/ai` types anywhere in this package.

---

## 5. Design principles

1. **Zero coupling:** This package never imports, type-hints, or references any `laravel/ai` class. The `UsageLogger` accepts primitives (strings, arrays, callables).
2. **User owns the glue:** The user writes the code that maps their AI client's response to our expected format. This keeps our package clean and works with any AI client.
3. **Reuse existing stack:** `UsageLogger` delegates entirely to `LogDraft`, `PricingEngine`, `PricingRepository`, and `ServiceUsageLog` — no parallel systems.
4. **No Composer dependency:** `laravel/ai` does not appear in `require`, `require-dev`, or `suggest`. The integration works through the user's own application code.
5. **Progressive adoption:** Users start with Level 1 (direct API) and graduate to Level 2-5 as needed.

---

## 6. Summary

- **Positioning:** Complements laravel/ai with usage logging, cost calculation, and video generation.
- **Delivery:** `UsageLogger` service, `TracksUsage` trait, and `RecordUsageMiddleware` — all with zero imports of external types. Duck typing handles response shape detection automatically.
- **Compatibility:** Zero dependency on laravel/ai. Works with any AI client that reports token usage.
- **Migration path:** Users already using this package's chat/image services keep working as-is. Users who prefer laravel/ai for chat can still use this package for observability and video.
