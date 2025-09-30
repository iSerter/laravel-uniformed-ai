# Feature Design: Token Usage & Cost Metrics

Status: Draft (v1)
Target Version: 0.x (post Service Usage Logs MVP)
Author: (Generated proposal)
Related Docs: `feature_service_usage_logs.md`

---

## 1. Purpose & Overview
Add an extensible, privacy‑aware mechanism to capture token usage and monetary cost for AI operations already logged by the Service Usage Logging subsystem. Provide accurate metrics when provider responses include usage sections; gracefully fall back to deterministic approximation when absent; and surface clear provenance / confidence indicators (reported vs estimated). Costs are computed from a configurable pricing matrix (per provider + model) with optional dynamic refresh hooks.

Initial scope focuses on text/chat style tokenized interactions (Chat `send()` & `stream()`), optionally Search (if provider returns token usage). Non‑text modalities (Image, Audio, Music) often price per unit (image, second, character) rather than tokens—these are deferred but the framework will allow later extension.

---

## 2. Goals
1. Accurately record prompt + completion token counts and total cost for each logged Chat operation.
2. Zero breaking changes to existing logging consumers; additive fields only.
3. Provide uniform field names regardless of provider field naming differences.
4. Distinguish provider‑reported vs approximated values (confidence metadata).
5. Fail safe: absence of usage data must not block persistence or raise user-facing errors.
6. Extensible pricing: static config + optional runtime hook (closure) to override model pricing (e.g., pulling from remote JSON or DB cache).
7. Minimal latency overhead (< 0.5ms typical for successful provider‑reported path; < 2ms when fallback tokenization executed on multi‑KB prompts).

Non-Goals (v1):
* Fine-grained per-message token breakdown.
* Real-time incremental token count during streaming (only final aggregated).
* Automatic currency conversion (single configured currency, e.g. USD).
* Image/audio cost rules (will be additive strategies later).

---

## 3. Terminology
| Term | Definition |
|------|------------|
| Prompt Tokens | Tokens attributed to input/prompt side (provider naming: prompt_tokens, input_tokens, usage.prompt_tokens, etc.). |
| Completion Tokens | Output/completion tokens (provider naming: completion_tokens, output_tokens). |
| Total Tokens | Sum of prompt + completion. |
| Reported | Token counts exactly returned by provider response payload. |
| Estimated | Approximate counts derived via local tokenizer fallback in absence of reported usage. |
| Pricing Matrix | Config structure mapping provider+model pattern to per-unit rates. |
| Confidence | Enum `reported|estimated|unknown`. |

---

## 4. Data Model / Persistence Strategy
Two-phase approach for backward compatibility + rapid iteration:

### Phase 1 (Immediate)
Store usage & cost metrics in existing `extra` JSON column of `service_usage_logs` under key `usage`:
```json
{
  "usage": {
    "prompt_tokens": 123,
    "completion_tokens": 456,
    "total_tokens": 579,
    "input_cost_cents": 1.23,
    "output_cost_cents": 3.70,
    "total_cost_cents": 4.93,
    "currency": "USD",
    "confidence": "reported",    // reported|estimated|unknown
    "pricing_source": "config:openai.gpt-4.1-mini@2025-09-01",
    "estimated_reason": null       // populated when confidence=estimated
  }
}
```

Advantages: no migration required; fast release.

### Phase 2 (Optional Future Migration)
Add dedicated columns if analytics volume demands indexing:
```
prompt_tokens (unsigned integer nullable)
completion_tokens (unsigned integer nullable)
total_tokens (unsigned integer nullable)
input_cost_cents (unsigned integer nullable)
output_cost_cents (unsigned integer nullable)
total_cost_cents (unsigned integer nullable)
cost_currency (string 8 nullable)
usage_confidence (string 12 nullable)
```
Phase 1 design intentionally mirrors names for painless backfill.

Decision: Implement Phase 1 now. Include doc block describing safe migration path.

---

## 5. Configuration Additions (`config/uniformed-ai.php`)
Extend existing `logging` section with a nested `usage` key (all additive). Pricing is sourced exclusively from the DB table `service_pricings`; no config fallback is used (migrations seed baseline rows).
```php
'logging' => [
    // ...existing keys...
    'usage' => [
        'enabled' => env('AI_USAGE_METRICS_ENABLED', true),
        // Only apply to these services initially
        'services' => [ 'chat' => true, 'search' => false, 'image' => false, 'audio' => false, 'music' => false ],
        // When provider usage absent, attempt local estimate
        'estimate_missing' => env('AI_USAGE_ESTIMATE_MISSING', true),
        // Tokenizer / model family for approximation (affects algorithm heuristics)
        'fallback_tokenizer' => env('AI_USAGE_FALLBACK_TOKENIZER', 'cl100k_base'),

  'pricing' => [], // ignored (legacy placeholder)
        // Rounding strategy for cents (bankers|ceil|floor). We store integer cents after rounding.
        'rounding' => env('AI_USAGE_COST_ROUNDING', 'bankers'),
        // Custom dynamic pricing resolver (callable FQCN or closure in service provider) – optional.
        'dynamic_pricing_resolver' => null,
        // Optional sampling (capture subset of successes; errors always captured)
        'sampling' => [
            'success_rate' => env('AI_USAGE_SAMPLE_SUCCESS_RATE', 1.0), // 0–1 float
            'error_rate'   => 1.0,
        ],
        // When true store raw provider usage subtree in `extra.usage.provider_raw`
        'store_provider_raw' => env('AI_USAGE_STORE_PROVIDER_RAW', false),
    ],
],
```

Validation: Add a lightweight config validator in service provider (only logs warnings on malformed pricing entries).

---

## 6. Pricing Resolution Algorithm
Inputs: provider slug, model string, pricing matrix, optional dynamic resolver.
Steps:
1. If dynamic resolver callable defined → invoke with `(provider, model)`; if returns non-null array use it (`pricing_source=dynamic:...`).
2. Query `PricingRepository::resolve(provider, model, serviceType)` (DB always authoritative). If found, use it (`pricing_source=db:<id>`).
3. If none found → cost cannot be computed; token counts still stored; `pricing_source = 'unpriced'`.
5. Validate unit: currently only `1K_tokens` supported; if different future units (e.g., `image`, `second`) appear but unsupported for service, skip cost computation.

Formula (unit=1K_tokens):
```
input_cost = (prompt_tokens / 1000) * input_rate
output_cost = (completion_tokens / 1000) * output_rate
total_cost = input_cost + output_cost
```
Converted to cents (multiply by 100) then rounded using configured strategy to integer; store as float in JSON? Decision: Store integer cents for precision, but to keep JSON consistent with possible future fractional currencies we will store **decimal with 2–6 places?**

Decision: Store numeric decimal (float) in JSON for readability. Provide helper accessor that returns Money-like struct later if needed. Still round to 1e-4 for stability. Example: 0.00493 dollars → 0.493 cents? Clarify units.

Design Choice:
* Store values in **cents** as decimal with at most 2 decimal places to avoid binary float issues (e.g. 4.93 = $0.0493). Field names include `_cents` to reflect integer semantics. Implementation converts to integer before persisting; JSON will show integer (e.g., 493). This matches existing example snippet (which displayed 4.93 but labeled `_cents`; adjust snippet for correctness).

Correction to Example (Section 4): Update to show integer cents:
```json
"input_cost_cents": 123,
"output_cost_cents": 370,
"total_cost_cents": 493,
```

---

## 7. Token Extraction Strategies
Different providers return usage in varied shapes.

| Provider | Expected Path(s) | Notes |
|----------|------------------|-------|
| OpenAI | `usage.prompt_tokens`, `usage.completion_tokens`, `usage.total_tokens` | Present for non‑stream & final chunk of streaming aggregated response (current HTTP streaming might omit — need final non-stream call or rely on driver aggregated raw). |
| OpenRouter | Mirrors OpenAI spec for many models; usage often at root `usage`. |
| Google (Gemini) | Not always present for free tiers; some endpoints provide `usageMetadata.promptTokenCount`, `...candidatesTokenCount`, `...totalTokenCount`. Mapping required. |
| Tavily | No token usage (skip). |
| ElevenLabs / Audio | Not token based. |

### Extraction Flow
1. Driver completes response (non-stream) → raw array available. Pass raw to `UsageMetricsCollector::fromRaw(provider, model, raw, requestDTO)`. Return struct with counts + `confidence=reported` if all counts present & numeric.
2. Streaming: existing driver accumulates final content & raw final payload (some providers do not send usage in stream). For providers lacking final usage, attempt fallback estimation if enabled.
3. Estimation: `TokenEstimator` counts tokens for prompt part & completion separately.
   * Inputs: array of messages (roles & content) & final completion string.
   * Algorithm (cl100k_base baseline): approximate tokens = ceil(chars / 4) with adjustments for JSON/object messages; for structured roles weight separators. Provide deterministic heuristics (documented) & unit tested allowed error band ±10% vs real tokenization for common short messages.
   * Future improvement: integrate external pure-PHP BPE tokenizer (if license/performance acceptable) to reduce error band.
4. Finalization: If only total tokens available (rare) → treat as `prompt_tokens = total - completion_tokens` when completion tokens present; else mark `confidence=unknown` without estimation unless config `estimate_missing=true`.

### Confidence Assignment
| Scenario | Confidence |
|----------|------------|
| All 3 counts provider reported | reported |
| Partial provider data + estimation fills missing | estimated |
| No provider data + estimation produced counts | estimated |
| No provider data + estimation disabled | unknown |

`estimated_reason` examples: `"provider_usage_missing"`, `"provider_usage_partial_missing_completion"`.

---

## 8. Lifecycle Integration (Logging Pipeline Changes)
Current flow (see usage logs design) creates `LogDraft` then finalizes. We extend `LogDraft`:
* New methods: `attachUsageMetrics(UsageMetrics $metrics)`.
* During success finalization:
  1. After response DTO sanitized, call `UsageMetricsCollector::collect($provider, $model, $rawResponse, $requestDTO, $finalContent?)`.
  2. If service not enabled or sampling skip triggered → no-op.
  3. Append metrics into `$draft->extra['usage']`.
* During error finalization:
  * If provider raw payload includes usage (some providers might still supply) attempt extraction unless disallowed.
  * Estimation for errors is optional; config chooses: estimation still helpful for debugging. Default: enabled (shared `estimate_missing`).
* Persistence unchanged; entire metrics object serialized inside `extra`.

Streaming nuance: Only compute after generator exhaustion OR error. If early termination detection yields `partial` status in future, metrics estimation includes only accumulated completion tokens (approximate) + `confidence=estimated` with reason `stream_partial`.

Sampling decision placed early (before estimation) to avoid wasted CPU. Implementation: randomly generate float 0–1; if > configured rate abort metrics collection (errors ignore sampling & always processed).

---

## 9. Component Design
### 9.1 UsageMetrics Value Object
```php
final class UsageMetrics {
    public function __construct(
        public ?int $promptTokens,
        public ?int $completionTokens,
        public ?int $totalTokens,
        public ?int $inputCostCents,
        public ?int $outputCostCents,
        public ?int $totalCostCents,
        public string $currency,
        public string $confidence, // reported|estimated|unknown
        public string $pricingSource,
        public ?string $estimatedReason = null,
        public ?array $providerRaw = null,
    ) {}

    public function toArray(): array { /* map to key => value */ }
}
```

### 9.2 UsageMetricsCollector Service
Responsibilities:
* Orchestrate extraction → estimation → pricing → confidence assignment.
* Public static entry `collect($provider, $model, array $raw, ChatRequest $request, ?string $completionText)` returning `?UsageMetrics`.
* Internally delegates to `ProviderUsageExtractor` & `TokenEstimator` & `PricingEngine`.

### 9.3 ProviderUsageExtractor
Map provider + raw payload to partial metrics (only counts & providerRaw). Switch or strategy map keyed by provider.

### 9.4 TokenEstimator
Implements heuristic counting (pluggable). Interface:
```php
interface TokenEstimator {
  public function estimatePromptTokens(ChatRequest $request): int;
  public function estimateCompletionTokens(string $completion): int;
}
```
Default: `HeuristicCl100kEstimator`.

### 9.5 PricingEngine
Input: provider, model, promptTokens, completionTokens.
Output: cost cents ints + pricingSource + currency.
Resolves pricing matrix (including wildcard) & calculates; returns null if pricing not found.

### 9.6 Rounding Helper
Implements bankers rounding:
```php
function roundBankers(float $value, int $precision = 0): float { /* typical implementation */ }
```
But since we return integer cents, we compute raw dollar cost then `cents = (int) roundBankers($dollars * 100)`.

### 9.7 Integration Points
* `AbstractLoggingDriver` (or `LogDraft`) extended with injection of `UsageMetricsCollector` (resolved via container for easier test swapping).
* Facade method (optional): `AI::usagePricing()` returning pricing matrix for introspection (low priority; maybe skip v1).

---

## 10. Error & Edge Case Handling
| Case | Behavior |
|------|----------|
| Provider usage fields missing | Attempt estimation if enabled; else confidence=unknown. |
| Provider returns only total tokens | Derive missing part if completion tokens present; else total only, confidence=reported (partial). |
| Estimation algorithm error | Catch & report; fallback to confidence=unknown. |
| Pricing missing | Tokens stored; cost fields null; pricingSource='unpriced'. |
| Negative or non-numeric provider counts | Discard as invalid; attempt estimation. |
| Streaming early termination | Use accumulated completion text for estimation; reason='stream_partial'. |
| Sampling skip (success) | Do nothing (omit usage entirely). |
| Error operation & sampling skip | Sampling ignored; always attempt metrics. |

---

## 11. Performance Considerations
* Provider-reported path: constant time dictionary extraction.
* Estimation path complexity O(N) where N char length of messages + completion; heuristic uses single pass per string.
* Wildcard pricing search linear in number of pricing entries for provider (usually small). Could precompile into array of compiled regex patterns cached statically.
* Memory footprint small (< few KB per operation). Provider raw usage optionally stored only when config enabled.
* Avoid multiple json_encode/decode cycles: operate on raw arrays before they are serialized into `extra`.

---

## 12. Security & Privacy
* Counts & pricing are non-sensitive.
* Provider raw usage subtree may include nothing sensitive (should be usage-only) but still passes through existing sanitization pipeline if stored.
* No secrets inspected beyond existing logging sanitization; token estimation never needs API keys.

---

## 13. Testing Strategy (Pest)
Test categories:
1. Provider extraction (OpenAI, OpenRouter, Google) mapping raw payload to correct counts.
2. Missing usage + estimation enabled vs disabled.
3. Pricing calculation (exact match, wildcard, dynamic resolver stub).
4. Rounding strategies (bankers, ceil, floor) produce expected integer cents.
5. Sampling skip (success) results in absent `extra.usage` but error still present.
6. Streaming finalization with estimated completion tokens.
7. Early termination partial (future) flagged `stream_partial` reason.
8. Invalid provider counts (negative) triggers estimation.
9. Performance micro-benchmark (optional) ensuring estimation under threshold for standard prompt size (~4KB).
10. Backward compatibility: absence of config keys still works (defaults loaded).

Test Helpers:
* Build fake driver returning controlled raw payload with/without usage.
* Utility to run `UsageMetricsCollector::collect()` directly.

---

## 14. Migration Path (Future Columns)
If analytics demands indexed queries on cost or tokens:
1. Create new migration adding numeric columns (see Section 4 Phase 2).
2. Add `UsageMetricsBackfill` artisan command scanning rows where `extra->usage` exists & new columns null, populating them in batches (chunking by id). Command idempotent.
3. Update model casts & optionally `ServiceUsageLog` accessors to surface both sources uniformly.
4. Mark JSON storage deprecated in docs (but still written for a transition release), then remove writing JSON after one major version.

---

## 15. Example JSON (Final)
```json
"extra": {
  "usage": {
    "prompt_tokens": 812,
    "completion_tokens": 164,
    "total_tokens": 976,
    "input_cost_cents": 122,
    "output_cost_cents": 98,
    "total_cost_cents": 220,
    "currency": "USD",
    "confidence": "reported",
    "pricing_source": "config:openai.gpt-4.1-mini@2025-09-01",
    "estimated_reason": null
  }
}
```

    ### 15.1 Baseline Seeded Pricing
    The migration `create_service_pricings_table` inserts initial reference pricing rows (cents per 1K tokens):

    | Provider | Model Pattern | Input (cents) | Output (cents) | Notes |
    |----------|---------------|---------------|----------------|-------|
    | openai | gpt-4.1-mini | 15 | 60 | Example placeholder; adjust to real pricing before production. |
    | openai | gpt-4o* | 500 | 1500 | Wildcard family (illustrative). |
    | google | gemini-1.5-flash | 7 | 30 | Example placeholder. |
    | openrouter | meta-llama/llama-3.1-8b-instruct | 20 | 40 | Example placeholder. |

    Administrators should update these to current official rates (or apply new migrations) as pricing evolves. Wildcards allow broad coverage; exact rows override wildcard matches due to precedence rules.

---

## 16. Open Questions
| Topic | Notes / Proposed Resolution |
|-------|-----------------------------|
| Multi-currency support | Defer; assume single currency across pricing matrix (USD). |
| Per-second / image pricing | Introduce additional units & strategy objects later. |
| Storing float vs integer cents | Adopt integer cents for precision (DB seeds store cents). |
| Real tokenizer integration | Evaluate lightweight PHP BPE library; if stable, replace heuristic for improved accuracy. |
| Partial streaming status dependency | Will align with eventual `partial` status implementation; until then treat as success & estimation reason. |
| Token usage for Search provider | Off by default; enable once a provider returns usage fields. |

---

## 17. Rollout Plan
1. Implement `UsageMetrics` VO + collector + extractor + pricing engine + estimator.
2. Seed baseline pricing in migration (OpenAI core models, Gemini, Llama example) — easily editable by subsequent manual inserts or new migrations.
3. Integrate into `LogDraft` finalize path (chat only).
4. Add tests (extraction, estimation, pricing resolution precedence: dynamic > db > unpriced).
5. Documentation updates (README section “Usage & Cost Metrics”).
6. Optional: Provide helper accessor on `ServiceUsageLog` (`usageMetrics(): ?UsageMetrics`).
7. Gather feedback, then evaluate adding columns (Phase 2) if needed.

---

## 18. Summary
This feature layers token usage & cost analytics onto the existing service usage logging system with minimal intrusion and strong extensibility. Provider data is preferred; deterministic estimation fills gaps. Pricing resolution is DB-first (seeded + versionable), with optional dynamic override. The design avoids schema changes initially, enabling rapid iteration while preserving a clear path to indexed columns and richer multi-unit cost support in future versions.
