# Feature Design: Configurable Custom Chat Profiles / Presets

> Goal: Provide a first‑class, ergonomic way to create reusable, named, task‑specific chat configurations ("custom GPT" style) with minimal boilerplate and without breaking the existing API.

---
## 1. Problem & Motivation
Today, every chat request requires explicitly constructing a `ChatRequest` with `messages`, `model`, maybe `tools`, temperature, etc. Applications often reuse the *same* combination: provider, model, system prompt, response format, temperature, tool set, safety settings, JSON schema, etc. Copying this logic everywhere causes:
- Repetition / risk of drift
- Harder experimentation (switch model/provider globally)
- No discoverable catalogue of internal “AI roles” (e.g., `AudienceProfilerChat`, `SummarizerChat`, `SQLHelperChat`)
- Harder to enforce policy (e.g., required safety preamble)

We want a light abstraction so developers can:
- Define **profiles** in config (fast, declarative)
- Optionally back them with **preset classes** (for logic / dynamic behavior)
- Use a **builder / fluent API** to create ad‑hoc one‑offs
- Keep full backward compatibility

---
## 2. Design Principles
1. Zero breaking changes; existing calls continue working.
2. Profiles are *just data*; Presets are *lightweight objects* wrapping that data with optional hooks.
3. Opt‑in: If you never use profiles, nothing changes.
4. Overridable at call site (last write wins) for quick experimentation.
5. Composable: a class preset can extend a config profile, or override fields.
6. Low cognitive overhead: read a profile, instantly know what it does.

---
## 3. Core Concepts
| Concept | Purpose |
|--------|---------|
| Chat Profile (config) | Named immutable base settings (provider, model, systemPrompt, defaults) registered via `config/uniformed-ai.php` |
| Chat Preset Class | PHP class representing a role/task. Can supply dynamic system prompt, mutate request pre‑send, post‑process response. |
| Registry | Service that can resolve a profile (config or class) by name. Caches instances. |
| Builder | Fluent object for on‑the‑fly composition (`AI::chat()->profile('audience_profiler')->with('temperature', 0.2)->send(...)`). |
| Response Format | Declarative structure (`json`, `json_schema`, `text`), unified across providers that support structured outputs. |

---
## 4. Configuration Additions
Add a new top‑level key `chat_profiles` inside `uniformed-ai.php`:
```php
'chat_profiles' => [
    // Simple declarative profile
    'audience_profiler' => [
        'provider' => 'openai',           // optional; defaults to chat default driver
        'model' => 'gpt-4.1-mini',        // optional; falls back to provider default
        'system_prompt' => 'You are an expert marketing analyst. Provide concise audience personas.',
        'temperature' => 0.3,
        'response_format' => 'json',      // 'text' | 'json' | ['json_schema' => [...]]
        'tools' => [ /* optional tool definitions (provider-agnostic shape) */ ],
        'max_tokens' => 800,
        'metadata' => ['category' => 'analysis'],
        // Policy markers / tags for internal governance
        'tags' => ['internal', 'marketing'],
    ],

    // Profile pointing to a preset class (allows hooks)
    'product_summarizer' => [
        'preset' => \App\AI\Chat\ProductSummarizerPreset::class,
        'provider' => 'openrouter',
        'model' => 'openrouter/auto',
        'temperature' => 0.2,
    ],
],
```

Schema summary:
```php
profile := [
  'preset'?: class-string<ChatPreset>,
  'provider'?: string,
  'model'?: string,
  'system_prompt'?: string|string[], // array joined with newlines
  'temperature'?: float,
  'max_tokens'?: int,
  'response_format'?: 'text'|'json'|['json_schema'=>array],
  'tool_choice'?: 'auto'|'none'|'required'|string,
  'tools'?: array<toolDefinition>,
  'metadata'?: array,
  'tags'?: array<string>,
  'overrides'?: array,   // optional provider-specific overrides
]
```

---
## 5. Class Presets
Introduce an abstract base class:
```php
abstract class ChatPreset
{
    public function __construct(protected array $profile) {}

    // Optionally provide / alter system prompt (can be dynamic by context)
    public function systemPrompt(?array $context = null): ?string { return $this->profile['system_prompt'] ?? null; }

    // Modify outbound ChatRequest (e.g., inject tools, forced metadata)
    public function prepareRequest(ChatRequest $req): ChatRequest { return $req; }

    // Post-process ChatResponse (e.g., JSON decode convenience, validation)
    public function transformResponse(ChatResponse $res): ChatResponse { return $res; }

    // Expose base profile raw settings
    public function settings(): array { return $this->profile; }
}
```
A concrete example:
```php
class AudienceProfilerPreset extends ChatPreset
{
    public function systemPrompt(?array $ctx = null): ?string
    {
        $locale = $ctx['locale'] ?? 'en';
        return "You are a marketing analyst. Locale: {$locale}. Output concise JSON with keys: audience, pain_points, tone.";
    }

    public function transformResponse(ChatResponse $res): ChatResponse
    {
        // If JSON mode requested, attach decoded payload
        if (($this->profile['response_format'] ?? null) === 'json' && $res->content) {
            $decoded = json_decode($res->content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $res->raw['decoded_json'] = $decoded;
            }
        }
        return $res;
    }
}
```

---
## 6. Registry & Resolution Flow
New service: `ChatProfileRegistry` (singleton). Responsibilities:
- Load config array once (lazy)
- Cache resolved preset instances per name
- Validate schema (throw `UniformedAIException` on invalid)
- Provide `get(string $name): ChatProfileDescriptor` where descriptor contains merged data:
  - `name`, `provider`, `model`, `baseSettings`, optional `presetInstance`

Manager integration:
1. `AI::chat()->profile('audience_profiler')` stores selected profile name in a lightweight ChatSessionBuilder
2. When `send()` or `stream()` invoked:
   - Registry descriptor pulled
   - Determine provider (profile.provider || default)
   - Get driver instance (existing manager mechanism)
   - Construct effective `ChatRequest`:
     - Prepend system prompt message if not explicitly included and available
     - Merge default temperature / model / tool config unless overridden in call
   - If preset class: run `prepareRequest()` before dispatch
3. After driver returns `ChatResponse`, run `transformResponse()` if preset

---
## 7. Builder / Fluent API
Add a `ChatSessionBuilder` returned by `ChatManager::profile()` OR `ChatManager::usingProfile()`:
```php
AI::chat()
  ->profile('audience_profiler')
  ->withContext(['locale' => 'de'])        // passes to systemPrompt()
  ->with('temperature', 0.1)               // ad-hoc override
  ->withTools([$tool1, $tool2])            // merge or replace
  ->responseFormat('json')                 // override profile
  ->sendMessages([
      new ChatMessage('user', 'Analyze eco-friendly travel audience.')
  ]);
```
Convenience shortcuts:
```php
AI::chat('audience_profiler')->ask('Describe the fitness tracker market.');
AI::chat()->profile('audience_profiler')->ask('Describe the fitness tracker market.');
```
`ask()` internally wraps message + optional system prompt into a `ChatRequest`.

---
## 8. Response Format Handling
Support field `response_format`:
- `'text'` (default) – no change
- `'json'` – For providers supporting JSON mode (OpenAI: `response_format => ['type' => 'json_object']`, others: adapt). If provider does not support, fallback & optionally warn via log.
- `['json_schema' => [...]]` – Map to provider-specific schema mechanism (OpenAI: `response_format => ['type' => 'json_schema','json_schema'=>...];`). If unsupported, throw or log depending on config flag `uniformed-ai.chat.strict_schema`.

Builder methods:
```php
->responseFormat('json')
->jsonSchema($schemaArray)
```

---
## 9. Modifications Required
### ChatManager
- Add method `public function profile(?string $name = null): ChatSessionBuilder`.
- Overload existing `chat($driver = null)` facade pattern so `AI::chat('audience_profiler')` resolves to builder if passed name matches a profile, else treat as driver.
- Provide `public function builder(): ChatSessionBuilder` for manual consumption.

### New Classes (names tentative)
- `Support/Chat/ChatProfileRegistry.php`
- `Support/Chat/ChatSessionBuilder.php`
- `Support/Chat/Presets/ChatPreset.php`

### Facade Adjustment
The anonymous facade accessor object can detect a profile name and delegate accordingly.

### Exception Types
- `ProfileNotFoundException extends UniformedAIException`
- `InvalidProfileException extends UniformedAIException`

### Service Provider
Register singletons:
```php
$this->app->singleton(ChatProfileRegistry::class, fn($app)=> new ChatProfileRegistry(config('uniformed-ai.chat_profiles', [])));
```

---
## 10. ChatSessionBuilder Responsibilities
```php
class ChatSessionBuilder {
    public function __construct(
        private ChatManager $manager,
        private ChatProfileRegistry $registry,
        private ?string $profileName = null
    ) {}

    public function profile(string $name): self;          // switch profile
    public function with(string $key, mixed $value): self; // generic override
    public function withContext(array $ctx): self;         // for dynamic system prompt
    public function withTools(array $tools): self;         // replace or merge
    public function responseFormat($format): self;         // text|json|schema
    public function jsonSchema(array $schema): self;       // convenience
    public function ask(string $userMessage): ChatResponse; // one-shot
    public function sendMessages(array $messages): ChatResponse;
    public function streamMessages(array $messages, ?Closure $cb = null): Generator;

    // Internals
    protected function buildRequest(array $messages): ChatRequest;
}
```
State stored in arrays; immutability could be added later (clone on write) if needed.

---
## 11. Merging Rules (Effective Request Assembly)
Priority (high -> low):
1. Explicit builder overrides (`with()`, `responseFormat()`, `jsonSchema()`)
2. Runtime call parameters (e.g., passing `model` directly to `sendMessages()` future variant)
3. Profile settings (config + preset modifications)
4. Provider defaults (existing behavior)

System Prompt Insertion:
- If profile or preset returns a system prompt AND first message role != `system`, prepend a `ChatMessage('system', $prompt)`.
- If user already included a system message, do nothing (idempotent).

Tools:
- Merge (unique by `name`) unless builder indicates replacement (e.g., second call to `withTools()` with flag `replace: true`). For simplicity initial version just replaces.

Response Format:
- Stored in builder. Adapter stage maps unified definition -> provider call payload modifications.

---
## 12. Edge Cases & Error Handling
| Case | Behavior |
|------|----------|
| Profile name not found | Throw `ProfileNotFoundException` |
| Invalid schema (e.g., unknown key type) | Throw `InvalidProfileException` on registry load |
| Provider lacks JSON mode | Log warning; fallback to text (config flag to force exception) |
| JSON schema too large / unsupported | Throw exception with provider context |
| Tool mismatch across providers | Builder passes tools; driver chooses mapping; unsupported fields dropped |
| Duplicate system message | Only auto-prepend if none present |
| Streaming with preset transform | Apply transform incrementally? (Phase 1: only on final aggregated response) |

---
## 13. Backward Compatibility & Migration
- No existing class signatures changed; new methods additive.
- `AI::chat()` unchanged; returns current manager (still works).
- `AI::chat('openai')` still forces driver selection (if name matches both a driver and profile, precedence rule: profile first OR introduce `AI::chatDriver('openai')`). Document decision (recommend: prefer profile; provide explicit `driver()` method to disambiguate).
- Users can incrementally move repeated patterns into `chat_profiles`.

---
## 14. Security & Governance Considerations
- Centralized profiles allow auditing of system prompts & whitelisted tools.
- `tags` support internal classification (e.g., PII sensitive tasks).
- Potential future: enforce only approved profiles in production via config flag.

---
## 15. Testing Strategy
1. Unit: Registry loads and validates profiles; error paths.
2. Unit: Builder merges overrides correctly.
3. Feature: Sending with profile inserts system prompt and model.
4. Feature: JSON response format triggers provider payload adaptation (OpenAI) & fallback on unsupported provider.
5. Feature: Preset dynamic prompt with context variable.
6. Edge: Duplicate system message not duplicated.

---
## 16. Future Enhancements
- Versioned profiles: `audience_profiler@v2`.
- Profile inheritance / extend (deep merge parent+child).
- Memory plugin: automatic conversation state caching per profile instance.
- Tool auto-loop: detect tool calls & re-inject tool outputs until completion.
- CLI generator: `php artisan ai:make-chat-preset AudienceProfiler`.
- Telemetry hooks (token count, latency) at preset level.
- Policy engine integration (approve prompts before dispatch).

---
## 17. Implementation Phases
| Phase | Scope |
|-------|-------|
| 1 | Core config schema + Registry + Builder (no JSON schema mapping yet) |
| 2 | Response format adapters (json + json_schema) |
| 3 | Preset hooks (prepareRequest / transformResponse) |
| 4 | CLI generator & advanced features |

---
## 18. Example End-to-End Usage
```php
// config/uniformed-ai.php
'chat_profiles' => [
    'audience_profiler' => [
        'provider' => 'openai',
        'model' => 'gpt-4.1-mini',
        'system_prompt' => 'You are an expert marketing analyst. Provide concise JSON.',
        'response_format' => 'json',
        'temperature' => 0.3,
    ],
];

// Controller or service
$response = AI::chat('audience_profiler')->ask('Analyze the target audience for a new vegan protein bar.');

$json = json_decode($response->content, true);

// Advanced with preset and context
$response = AI::chat()
    ->profile('audience_profiler')
    ->withContext(['locale' => 'es'])
    ->with('temperature', 0.15)
    ->ask('Perfil del público para una app de meditación.');
```

---
## 19. Open Questions
- Should profile vs driver name collision prefer driver? (Leaning: PROFILE first for feature emphasis; provide explicit `driver()` for clarity.)
- Streaming transform: Provide optional `onDeltaTransform()` hook later?
- Should builder be immutable (chain returns clone)? (Maybe later for thread safety.)

---
## 20. Summary
This feature introduces **Chat Profiles** (declarative) and **Chat Presets** (class-based) with a **fluent builder**, enabling reusable, auditable, and composable chat configurations—drastically reducing boilerplate while preserving the existing, simple API. It sets a foundation for future structured output support, governance, and advanced automation loops.
