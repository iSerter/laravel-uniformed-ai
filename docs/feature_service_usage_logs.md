# Feature Design: AI Operation Usage Logging (Revised)

This document supersedes the earlier "AI Usage Logs" draft. It tightens terminology, clarifies lifecycle, and introduces a consistent naming scheme to reduce ambiguity and ease future extension (tokens, cost metrics, sampling, alternative sinks).

## 1. Purpose & Scope
Deliver an opt‑in, low‑overhead observability layer that records each *AI operation* (chat send/stream, image generate, audio synthesize, music compose, search query) in a structured, privacy‑aware form. The log enables:
* Debugging & incident forensics
* Auditing & accountability (who invoked what, when, with which provider/model)
* Latency & reliability analysis
* Future analytics (token, cost, success rates, drift, sampling)

Primary constraints: zero impact on functional correctness, minimal synchronous latency (<2ms CPU target), strict secret redaction, graceful degradation on failure.

---

## 2. Terminology (Canonical)
| Term | Definition |
|------|------------|
| Operation | A single high‑level API call (e.g. Chat::send, Image::create). |
| Log Record | One persisted row representing an operation outcome. |
| Provider | External AI platform (openai, openrouter, google, tavily, elevenlabs, etc.). |
| Service | Functional domain (chat, image, audio, music, search). Stored as `service`. |
| Driver | Implementation class wrapping a provider for a service. |
| Draft (Pending Log) | In‑memory accumulator before persistence. |
| Streaming Delta | Incremental chunk of streamed content. |
| Sanitization | Redaction + truncation + structural normalization before persistence. |
| Persistence Mode | Sync (inline) or async (queued job). |

---

## 3. Design Goals
1. Consistent, evolvable data model (additive columns only; historic queries remain valid).
2. Centralized lifecycle: start → measure → finalize → persist (success or error) with minimal duplication.
3. Defensive privacy posture (never store secrets; robust heuristics + regex patterns).
4. Configurability: global toggle, per‑service enablement, queue off/on, truncation thresholds, pruning policy.
5. Non‑intrusive: logging failures never surface to caller; they are reported only.
6. Extensible: generic `extra` JSON + pluggable enrichment (future token usage, cost, correlation IDs, sampling).

---

## 4. Functional Requirements (Refined)
We log exactly one record per operation:

| Service | Operations Logged |
|---------|-------------------|
| Chat | `send()`, `stream()` (single record capturing final aggregated content) |
| Image | `create()`, `modify()`, `upscale()` |
| Audio | `speak()` |
| Music | `compose()` |
| Search | `query()` |

Captured fields (sanitized & possibly truncated):
* `service_type` (chat, image, audio, music, search)
* `service_operation` (send, stream, create, modify, upscale, speak, compose, query, etc.)
* `provider` (slug, e.g. `openai`)
* `model` (nullable)
* `status` (`success | error | partial`)  – optional future extensions: `cancelled`, `timeout`
* `http_status` (nullable integer)
* `latency_ms`
* `started_at`, `finished_at`
* `request_payload` (sanitized structured array)
* `response_payload` (sanitized structured array)
* `error_message`, `error_class`, `exception_code` (on failure)
* `stream_chunks` (optional array of truncated deltas when enabled)
* `extra` (JSON; free‑form enrichment)
* `user_id` (nullable; from `Auth::id()`)

Behavioral specifics:
* Streaming: only one record; default stores final concatenated content. Chunk capture optional via config.
* Error path: always persist a record with `status=error` even if the underlying driver throws.
* Partial Support: if a streaming sequence aborts mid‑stream (consumer stops iterating) we can mark `status=partial` (MVP can mark `success`; improvement flagged—see Open Questions).
* Redaction is mandatory and unconditional (not user‑toggleable) for known key patterns; additional heuristic redaction can be disabled in future if needed.
* Large fields are truncated with suffix `...(truncated)` while preserving structural validity.
* Logging never alters original DTOs or driver responses.

---

## 5. Non‑Functional Requirements (Refined)
* Performance: <2ms CPU overhead synchronous (excludes DB I/O). Target 1 extra object allocation pass.
* Resilience: swallow and `report()` internal logging exceptions; never throw to caller.
* Storage Efficiency: minimal indexing (provider, service, user_id, created_at, model). All variable size content in JSON or TEXT.
* Security: zero raw secrets; strong pattern library; redaction occurs prior to serialization.
* Compatibility: Laravel 11 & 12; queue optional; works with MySQL & Postgres.
* Observability Evolution: schema anticipates token/cost addition with additive columns or `extra` keys.

---

## 6. Data Model / Schema
Default table name: `service_usage_logs` (configurable).

Columns:
```
id (big increments)
user_id (nullable unsigned big integer) indexed
service_type (string 20) indexed   // chat, image, etc
service_operation (string 20)
provider (string 40) indexed
model (string 120 nullable) indexed
status (string 16) enum-like values: success, error, partial
http_status (smallInteger nullable)
latency_ms (unsigned integer nullable)
started_at (timestamp)
finished_at (timestamp nullable)
request_payload (json nullable)
response_payload (json nullable)
error_message (text nullable)
error_class (string 160 nullable)
exception_code (integer nullable)
stream_chunks (json nullable)
extra (json nullable)
created_at, updated_at (timestamps)
```

Indexes:
* `idx_service_usage_logs_provider_service` (provider, service_type, created_at)
* `idx_service_usage_logs_user` (user_id, created_at)
* `idx_service_usage_logs_model` (model)

Future / Optional:
* Partial index on `(status)` = 'error' (Postgres) for faster error analytics.
* Token/cost columns (prompt_tokens, completion_tokens, total_tokens, cost_cents) – OR enriched `extra`.

---

## 7. Configuration (Enhanced)
Config block (existing key `logging` retained). Naming suggestions below for clarity (keeping env names stable). Corrections applied: removed duplicated nested `env()` invocations in pruning defaults.

```php
'logging' => [
    'enabled' => env('SERVICE_USAGE_LOG_ENABLED', true),
    'connection' => env('SERVICE_USAGE_LOG_CONNECTION', null), // database connection name or null (default)
    'table' => env('SERVICE_USAGE_LOG_TABLE', 'service_usage_logs'),

    // Per-service toggles (optional future): 'services' => ['chat' => true, 'image' => true, ...]

    'queue' => [
        'enabled' => env('SERVICE_USAGE_LOG_QUEUE', false),
        'connection' => env('SERVICE_USAGE_LOG_QUEUE_CONNECTION', null),
        'queue' => env('SERVICE_USAGE_LOG_QUEUE_NAME', 'ai-usage-logs'),
        // Optional future: 'batch_size' => 1 (aggregate flush)
    ],

    'truncate' => [
        'request_chars' => env('SERVICE_USAGE_LOG_TRUNCATE_REQUEST', 20000),
        'response_chars' => env('SERVICE_USAGE_LOG_TRUNCATE_RESPONSE', 40000),
        'chunk_chars' => env('SERVICE_USAGE_LOG_TRUNCATE_CHUNK', 2_000),
    ],

    'stream' => [
        'store_chunks' => env('SERVICE_USAGE_LOG_STREAM_STORE_CHUNKS', true),
        'max_chunks' => env('SERVICE_USAGE_LOG_STREAM_MAX_CHUNKS', 500), // safety cap
    ],

    'prune' => [
        'enabled' => env('SERVICE_USAGE_LOG_PRUNE_ENABLED', true),
        'days' => env('SERVICE_USAGE_LOG_PRUNE_DAYS', 30),
    ],

    'redaction' => [
        'mask' => env('SERVICE_USAGE_LOG_REDACTION_MASK', '***REDACTED***'),
        // Additional patterns can be appended via config override or runtime hook.
    ],
];
```

Backward compatibility: existing apps referencing earlier keys continue to work; new keys (`stream.store_chunks`, `redaction.mask`) are additive.

---

## 8. Lifecycle & Control Flow
1. API call made (e.g. `AI::chat()->send($request)`).
2. Manager resolves provider driver.
3. If logging enabled for service, driver is wrapped by a `Logging*Driver` decorator produced by `LoggingDriverFactory`.
4. Decorator instantiates a `LogDraft` (formerly `ServiceUsageLogDraft`) with sanitized/truncated request payload + timestamps start.
5. Execution timed. On completion or exception:
   * Success → finalize with sanitized response, compute `latency_ms`, mark status `success`.
   * Exception → capture metadata (class, message/code), mark status `error`.
6. Streaming variant: wrapper yields deltas; accumulator builds final content and optionally chunk list. Early termination can mark `partial` (future refinement; MVP may still mark `success`).
7. Persistence path:
   * Queue enabled → dispatch `PersistServiceUsageLog` job with array payload.
   * Else → synchronous `ServiceUsageLog::create()`.
8. Any internal error inside steps 4–7 is reported then suppressed.

Sequence Diagram (conceptual):
```
Caller -> Manager -> (Driver|LoggingDecorator) -> Provider API
  |           |           |                         |
  |        create draft   |                         |
  |           |        execute + time -------------->
  |           |        finalize / error             |
  |           |        queue or sync persist        |
```

---

## 9. Architecture Components
| Component | Responsibility |
|-----------|----------------|
| `AbstractLoggingDriver` | Shared orchestration (draft start, timing, finalize, streaming adaptation, persistence). |
| `LoggingChatDriver` (and peers) | Thin adapters implementing service contracts, delegating to inner driver and abstract base helpers. |
| `LogDraft` (rename of `ServiceUsageLogDraft`) | Accumulate state pre‑persistence; apply sanitization + truncation only once. |
| `SanitizesPayloads` trait | Redaction + truncation utilities (pure functions where possible). |
| `PersistServiceUsageLogJob` | Async persistence job; swallow errors. |
| `LoggingDriverFactory` | Conditional wrapping logic + service enablement checks. |
| `PruneServiceUsageLogs` command | Deletes aged records based on config policy. |

Naming Rationale: `LogDraft` is shorter, less redundant than `ServiceUsageLogDraft` yet unambiguous in the logging namespace.

---

## 10. Sanitization & Redaction Strategy
Pipeline (idempotent): Normalize → Redact → Truncate.

Redaction layers:
1. Key Name Based (case‑insensitive): `api_key`, `authorization`, `auth`, `secret`, `token`, `key`, `password`, `access_token`, `bearer`.
2. Regex Patterns (examples):
   * OpenAI: `/sk-[A-Za-z0-9]{20,}/`
   * Google API: `/^AIza[\w-]{30,}$/`
   * Generic Hex: `/\b[0-9a-fA-F]{32,64}\b/`
   * Slack‑style: `/xox[baprs]-[A-Za-z0-9-]{10,}/`
3. Heuristics: long opaque strings (length ≥ 24, high entropy, limited alphabet) flagged & masked.

Truncation rules (character counts after redaction):
* Request fields: global cap `request_chars` (aggregate serialized JSON string length heuristic).
* Response fields: cap `response_chars`.
* Stream chunks: each chunk individually limited to `chunk_chars`; final concatenated payload separately truncated by `response_chars`.

Mask Format: replace full value with configured `'***REDACTED***'`. (Future: preserve last 4 chars using `'***...abcd'` variant.)

Deterministic application ensures consistent testability.

---

## 11. Streaming Handling
Two accumulation strategies (config driven):
* `capture_chunks = true`: store array `stream_chunks` of per‑delta truncated values + build final string.
* `capture_chunks = false`: append directly to final string, skip per-chunk storage to reduce size.

Safety Controls:
* `max_chunks` hard cap (default 500) – further deltas ignored (status may become `partial`).
* Memory: chunk truncation prevents large token floods.

---

## 12. Persistence & Failure Semantics
* Sync path is simple Eloquent `create` within a try/catch.
* Async path serializes array state (no serializing closures / objects) to queued job.
* Failures (DB, serialization) → `report()`; the system never retries automatically (user can configure queue retry policy separately).
* No guarantee of ordering across async writes; consumers relying on sequences should order by `started_at`.

---

## 13. Pruning
Artisan command: `ai-usage-logs:prune`.
* Deletes records where `created_at < now()->subDays(days)`.
* Guard clause if pruning disabled.
* Emits info log with count deleted (for observability). Silent success otherwise.
* Future: optional soft retention (copy to cold storage, then delete) – backlog item.

---

## 14. Testing Strategy (Expanded)
Using Pest:
1. Migration existence & column presence.
2. Success path (chat send): asserts status, model, latency >0, sanitized request (no plain API key patterns).
3. Error path: throw `ProviderException`; record reflects `error_class`, `status=error` and still persisted.
4. Streaming success: 3 deltas produce final concatenated payload; optional `stream_chunks` count matches.
5. Streaming early termination: consumer breaks early → (future) `partial` status expectation (MVP: document behavior; test pending once implemented).
6. Redaction: embed known patterns; assert all replaced with mask.
7. Truncation: large prompt & b64 field; verify suffix.
8. Disabled global logging: no row created.
9. Per-service disable (if implemented): only targeted service suppressed.
10. Queue mode: fake bus; assert job dispatched with sanitized array (no objects).
11. Prune command: seed old/new; run command; assert only recent remain.
12. Logging failure swallow: mock model to throw; ensure no exception surfaces to caller.

---

## 15. Performance Considerations
* Single pass sanitization (avoid repeated json_encode/decode cycles).
* Use static compiled regex list to prevent recompilation overhead.
* Defer heavy string operations (e.g. entropy heuristic) until after cheap key/pattern matches.
* Avoid storing huge arrays: optional chunk capture off by default in high‑volume environments (document recommendation).

---

## 16. Extensibility Path
Immediate future (non‑breaking):
* Token & cost metrics enrichment via pluggable middleware (adds keys under `extra`).
* Correlation ID injection (trace_id) from request context.
* Sampling rate config (`sample_rate_success`, `sample_rate_error=1.0`).
* Alternate sink interface (e.g. implement `LogSinkContract` for ClickHouse / OpenSearch; DB remains default).

---

## 17. Open Questions / Decisions Pending
| Topic | Decision Status |
|-------|-----------------|
| `partial` status for early stream termination | MVP: may map to success; finalize post initial implementation. |
| Rename DB column `service_type` → `service` | Defer (introduce accessor & future migration). |
| Token metrics schema vs `extra` | Start in `extra`; migrate to columns only if query volume justifies. |
| Configurable redaction disable | Not allowed (security stance). |
| Batch async persistence | Defer until perf evidence. |

---

## 18. Migration Stub (Reference)
```php
Schema::create(config('uniformed-ai.logging.table', 'service_usage_logs'), function (Blueprint $t) {
    $t->bigIncrements('id');
    $t->unsignedBigInteger('user_id')->nullable()->index();
    $t->string('provider', 40)->index();
    $t->string('service_type', 20)->index();
    $t->string('driver', 120);
    $t->string('model', 120)->nullable()->index();
    $t->string('status', 16);
    $t->smallInteger('http_status')->nullable();
    $t->unsignedInteger('latency_ms')->nullable();
    $t->timestamp('started_at');
    $t->timestamp('finished_at')->nullable();
    $t->json('request_payload')->nullable();
    $t->json('response_payload')->nullable();
    $t->text('error_message')->nullable();
    $t->string('error_class', 160)->nullable();
    $t->integer('exception_code')->nullable();
    $t->json('stream_chunks')->nullable();
    $t->json('extra')->nullable();
    $t->timestamps();
    $t->index(['provider','service_type','created_at'],'idx_service_usage_logs_provider_service');
});
```

---

## 19. Example Abstract Logging Base (Refined)
```php
abstract class AbstractLoggingDriver
{
    public function __construct(
        protected string $provider,
        protected string $service,
    ) {}

    protected function startDraft(?string $model = null, array $requestPayload = []): LogDraft
    {
        return LogDraft::start(
            service: $this->service,
            provider: $this->provider,
            request: $requestPayload,
            model: $model,
        );
    }

    /**
     * @template TResponse
     * @param callable():TResponse $execute
     * @param (callable(TResponse):mixed)|null $transform Stored version transformer
     * @return TResponse
     */
    protected function runOperation(LogDraft $draft, callable $execute, ?callable $transform = null)
    {
        try {
            $response = $execute();
            $draft->finishSuccess($transform ? $transform($response) : $response);
            return $response;
        } catch (\Throwable $e) {
            $draft->finishError($e);
            throw $e;
        } finally {
            $this->persistDraft($draft);
        }
    }

    protected function runStreaming(LogDraft $draft, Generator $generator, ?callable $onDelta = null, bool $captureChunks = true): Generator
    {
        try {
            foreach ($generator as $delta) {
                $captureChunks ? $draft->accumulateChunk($delta) : $draft->appendToFinal($delta);
                if ($onDelta) { $onDelta($delta); }
                yield $delta;
            }
            $draft->finishSuccessStreaming();
        } catch (\Throwable $e) {
            $draft->finishError($e);
            throw $e;
        } finally {
            $this->persistDraft($draft);
        }
    }

    protected function persistDraft(LogDraft $draft): void
    {
        try { $draft->persist(); } catch (\Throwable $e) { report($e); }
    }
}
```

---

## 20. Security & Privacy Summary
* Secrets masked before serialization.
* No raw API keys or bearer tokens written.
* Truncated artifacts prevent accidental large sensitive content retention.
* Redaction logic is deterministic & test‑covered.
* Future: allow user‑provided additional redaction patterns via config closure.

---

## 21. Rollout Plan
1. Implement schema + model + config keys.
2. Introduce `LoggingDriverFactory` + abstract base + chat decorator only (incremental release).
3. Add remaining service decorators.
4. Add queue job + pruning command.
5. Complete test matrix.
6. Documentation & upgrade notes.
7. Optional: add sampling & partial stream status in follow‑up minor release.

---

## 22. Backward Compatibility & Migration Strategy
* Initial release introduces new table; no BC concerns.
* If future rename `service_type` → `service`, create new nullable column and backfill; keep legacy column until major version.
* Additive config keys only; default behaviors remain unchanged for existing installations upgrading patch/minor versions.

---

## 23. Future Enhancements (Curated)
Shortlist (prioritized):
1. Token/Cost Enrichment
2. Sampling (success operations) with per‑service overrides
3. External Sink Interface (ClickHouse / OpenSearch)
4. Correlation / Trace IDs
5. Telescope / Horizon panel integration
6. CSV / JSONL export command
7. Cold storage archiving (S3) for >N days
8. Field‑level encryption for sensitive tokens (if business cases emerge)

---

## 24. Summary
The revised design establishes a concise, extensible, and privacy‑first logging subsystem for AI operations. By consolidating lifecycle logic in `AbstractLoggingDriver` and deferring heavy work (redaction, truncation) to a single pass, it minimizes overhead while providing rich analytic potential. Naming clarifications (`service`, `LogDraft`, `driver_class`) and structured configuration reduce future migration friction. The system is intentionally incremental—shipping core chat logging first enables rapid feedback before expanding across all services and adding advanced analytics.


## 2. Requirements
### Functional
- Log one row per logical AI operation: Chat `send()`, Chat `stream()` (final aggregated response), Image `create|modify|upscale`, Audio `speak`, Music `compose`, Search `query`.
- Capture: provider key (e.g. `openai`), service type (`chat|image|audio|music|search`), driver class, model (if present), request DTO (sanitized), response DTO or error (sanitized), status (`success|error|partial`), HTTP status (if known), latency ms, started_at / finished_at timestamps.
- Capture authenticated user id (`Auth::id()`) if present; nullable otherwise.
- Streaming: accumulate deltas server-side; store final concatenated content only by default. Configurable option to also persist incremental chunks (array) or truncated preview.
- Errors: always create a log row with `status=error`, store exception class, message, code, provider, and raw payload (sanitized) if available.
- Configuration toggle to enable/disable logging globally and per service type (e.g. disable image logs to reduce storage).
- Ability to mask / redact secrets (API keys, bearer tokens, values that look like keys) in request/response JSON before persisting. Must mask API keys if they appear in request payloads.
- Truncation limits for large prompt/content or image b64 fields with configurable max sizes (chars). Indicate truncation via suffix `... (truncated)`.
- Optional async persistence via queue (dispatch job) to minimize latency impact; synchronous fallback if queue disabled or unavailable.
- Configurable table name and database connection.
- Pruning strategy: artisan command to delete logs older than configurable days; ability to disable pruning.
- Provide Eloquent model `ServiceUsageLog` for easy querying.
- Provide facade/helper to query recent logs for debugging (future optional; not core to MVP).

### Non-Functional
- Overhead: synchronous logging target <2ms incremental CPU (excludes DB IO). Async option recommended for production.
- Storage efficiency: JSON columns, selective truncation, indexing only necessary fields (provider, service_type, user_id, created_at).
- Security: never store raw API keys. Redaction step runs before serialization. Defensive pattern matching for typical key formats (sk_*, xoxb-, etc.).
- Resilience: logging failures must not break the main AI call. Fail silent (reportable event / log channel) but swallow exceptions.
- Extensibility: support arbitrary future metadata (token_usage, cost) via `extra` JSON column.

### Implicit
- Works across Laravel 11 & 12 (same as package baseline).
- Compatible with default migration publishing flow.
- No hard dependency on queue; gracefully degrades.

## 3. Database Schema
Migration (publish with tag `uniformed-ai-migrations`). Table name default: `service_usage_logs`.

Columns (MySQL / Postgres friendly):
- id (big increments)
- user_id (nullable unsigned big integer) + index
- service_type (string 20) index
- provider (string 40) index
- model (string 120 nullable) index
- status (enum/string 16) values: success, error, partial
- http_status (smallInteger nullable)
- latency_ms (unsigned integer nullable)
- started_at (timestamp)
- finished_at (timestamp nullable)
- request_payload (json nullable)
- response_payload (json nullable)
- error_message (text nullable)
- error_class (string 160 nullable)
- exception_code (integer nullable)
- stream_chunks (json nullable)  // optional storage for streaming deltas (if enabled)
- extra (json nullable)          // future: token usage, cost, etc.
- created_at, updated_at (timestamps)

Indexes:
- idx_service_usage_logs_provider_service (provider, service_type, created_at)
- idx_service_usage_logs_user (user_id, created_at)
- idx_service_usage_logs_model (model)

Notes:
- Consider partial index where status='error' if large volume & error analytics needed (Postgres).
- For MySQL, use generated column for date partitioning if needed (future).

## 4. Config Additions (`config/uniformed-ai.php`)
```php
'logging' => [
    'enabled' => env('SERVICE_USAGE_LOG_ENABLED', true),
    'connection' => env('SERVICE_USAGE_LOG_CONNECTION', null), // null = default connection
    'table' => env('SERVICE_USAGE_LOG_TABLE', 'service_usage_logs'),
    'queue' => [
        'enabled' => env('SERVICE_USAGE_LOG_QUEUE', false),
        'connection' => env('SERVICE_USAGE_LOG_QUEUE_CONNECTION', null),
        'queue' => env('SERVICE_USAGE_LOG_QUEUE_NAME', 'ai-usage-logs'),
    ],
    'truncate' => [
        'request_chars' => env('SERVICE_USAGE_LOG_TRUNCATE_REQUEST', 20000),
        'response_chars' => env('SERVICE_USAGE_LOG_TRUNCATE_RESPONSE',40000),
        'chunk_chars' => env('SERVICE_USAGE_LOG_TRUNCATE_CHUNK', 2000),
    ],
    'prune' => [
        'enabled' => env('SERVICE_USAGE_LOG_PRUNE_ENABLED', env('SERVICE_USAGE_LOG_PRUNE_ENABLED', true)),
        'days' => env('SERVICE_USAGE_LOG_PRUNE_DAYS', env('SERVICE_USAGE_LOG_PRUNE_DAYS', 30)),
    ],
],
```


## 5. Logging Pipeline & Integration
Flow per operation:
1. Caller invokes e.g. `AI::chat()->send($request)`.
2. Manager resolves driver. We wrap driver in a lightweight decorator if logging enabled for that service.
3. Decorator creates an in-memory log draft (started_at=now, request DTO serialized via normalizer -> array -> sanitize -> truncate).
4. Executes the underlying driver inside try/catch + timing.
5. On success: finalize fields (status=success, response_payload sanitized + truncated, finished_at, latency_ms).
6. On error: capture exception metadata (status=error, error_message, error_class, code, provider). Attempt to include sanitized provider raw error if available.
7. Dispatch persistence: synchronously save via `ServiceUsageLog::create($data)` OR queue `PersistServiceUsageLogJob` if queue enabled.
8. Streaming: decorator intercepts generator; as we iterate, optionally accumulate deltas (respect max_chunks & truncation). Provide a wrapper generator that yields each delta to caller, while capturing for final log. After completion or early break, finalize response content and log.
9. Any logging failure is caught; we `report()` but never throw.

Decorator Implementation Strategy:
- Introduce interface `LogsAiOperations` (optional) or directly implement per service wrapper class (`LoggingChatDriver`, `LoggingImageDriver`, etc.) that accepts the concrete driver + context metadata (provider name, service type).
- Manager modification: when resolving driver (in `driver()` or `createXDriver()`), if logging enabled for service then return `new LoggingChatDriver($baseDriver, 'openai')` for example.
- Provider name inference: from the driver method name the Manager originally used (e.g., `createOpenaiDriver` -> 'openai'). Could pass explicitly in Manager factory.

#### Base Abstraction To Reduce Duplication
Rather than replicating identical lifecycle (draft start, timing, success/error finalization, persistence, silent failure) across multiple logging decorators, introduce `AbstractLoggingDriver` that encapsulates shared helpers. Each concrete logging driver remains thin and focused only on adapting its specific contract methods.

Goals:
- Centralize logging lifecycle & error swallowing policy.
- Provide unified helpers for synchronous and streaming operations.
- Simplify future cross-cutting additions (sampling, correlation IDs, token metrics) in one place.

Sketch:
```php
abstract class AbstractLoggingDriver
{
    public function __construct(
        protected string $provider,
        protected string $serviceType,
    ) {}

    protected function startDraft(object $innerDriver, ?string $model = null, array $requestPayload = []): ServiceUsageLogDraft
    {
        return ServiceUsageLogDraft::start(
            serviceType: $this->serviceType,
            provider: $this->provider,
            request: $requestPayload,
            model: $model,
        );
    }

    protected function runOperation(ServiceUsageLogDraft $draft, callable $execute, ?callable $transform = null)
    {
        try {
            $response = $execute();
            $draft->finishSuccess($transform ? $transform($response) : $response);
            return $response;
        } catch (\Throwable $e) {
            $draft->finishError($e);
            throw $e; // propagate
        } finally {
            $this->persistDraft($draft);
        }
    }

    protected function runStreaming(ServiceUsageLogDraft $draft, Generator $generator, ?callable $onDelta = null, bool $captureChunks = true): Generator
    {
        try {
            foreach ($generator as $delta) {
                if ($captureChunks) { $draft->accumulate($delta); }
                else { $draft->appendToFinal($delta); }
                if ($onDelta) { $onDelta($delta); }
                yield $delta;
            }
            $draft->finishSuccessStreaming();
        } catch (\Throwable $e) {
            $draft->finishError($e);
            throw $e;
        } finally {
            $this->persistDraft($draft);
        }
    }

    protected function persistDraft(ServiceUsageLogDraft $draft): void
    {
        try { $draft->persist(); } catch (\Throwable $e) { report($e); }
    }
}
```

Concrete example (Chat):
```php
class LoggingChatDriver extends AbstractLoggingDriver implements ChatContract
{
    public function __construct(private ChatContract $inner, string $provider)
    { parent::__construct($provider, 'chat'); }

    public function send(ChatRequest $request): ChatResponse
    {
        $draft = $this->startDraft($this->inner, $request->model, get_object_vars($request));
        return $this->runOperation($draft, fn() => $this->inner->send($request));
    }

    public function stream(ChatRequest $request, ?Closure $onDelta = null): Generator
    {
        $draft = $this->startDraft($this->inner, $request->model, get_object_vars($request));
        $gen = $this->inner->stream($request, $onDelta);
        return $this->runStreaming($draft, $gen, $onDelta, captureChunks: config('uniformed-ai.logging.store_stream_chunks', true));
    }
}
```

Outcome: repeated try/catch and persistence blocks disappear from service-specific classes, reducing maintenance overhead and potential inconsistencies.

Serialization & Sanitization:
- Convert DTO public properties to arrays (simple reflection or `get_object_vars`).
- Recursively traverse arrays/objects; for keys matching redaction keys list OR values matching regex patterns (predefined + custom) OR value length > plausible key heuristics (e.g. starts with `sk-`, `rk_`, `pk_`), replace value with mask.
- Large binary/base64 fields: if size > limit → truncate + suffix.

Streaming Wrapper Pseudocode:
```php
return (function() use ($gen, &$accumulator, $cfg) {
    foreach ($gen as $delta) {
        if ($storeChunks) {
            $chunk = Str::limit($delta, $chunkLimit, '...(truncated)');
            $accumulator[] = $chunk;
            if (count($accumulator) >= $maxChunks) break; // hard stop to avoid runaway
        }
        $finalContent .= $delta;
        yield $delta; // pass through
    }
})();
```
Finalize after generator completes or is closed.

## 6. Implementation Components (Pseudocode)
### Model: `src/Models/ServiceUsageLog.php`
```php
class ServiceUsageLog extends Model {
    protected $table; // set in boot from config
    protected $guarded = [];
    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'stream_chunks' => 'array',
        'extra' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
```

### Migration Stub (table: service_usage_logs)
```php
Schema::create(config('uniformed-ai.logging.table', 'service_usage_logs'), function (Blueprint $t) {
    $t->bigIncrements('id');
    $t->unsignedBigInteger('user_id')->nullable()->index();
    $t->string('provider', 40)->index();
    $t->string('service_type', 20)->index();
    $t->string('driver', 120);
    $t->string('model', 120)->nullable()->index();
    $t->string('status', 16);
    $t->smallInteger('http_status')->nullable();
    $t->unsignedInteger('latency_ms')->nullable();
    $t->timestamp('started_at');
    $t->timestamp('finished_at')->nullable();
    $t->json('request_payload')->nullable();
    $t->json('response_payload')->nullable();
    $t->text('error_message')->nullable();
    $t->string('error_class', 160)->nullable();
    $t->integer('exception_code')->nullable();
    $t->json('stream_chunks')->nullable();
    $t->json('extra')->nullable();
    $t->timestamps();
    $t->index(['provider','service_type','created_at'],'idx_service_usage_logs_provider_service');
});
```

### Sanitizer Utility
`SanitizesPayloads` trait with method `sanitize(array $data): array` applying:
- Key-based redaction.
- Regex patterns (compiled once, static cache).
- Heuristic redaction (strings >12 containing 3+ segments of alnum with dashes, etc.).
- Truncation using config limits.

### Decorator Example (Chat)
```php
class LoggingChatDriver implements ChatContract {
    public function __construct(private ChatContract $inner, private string $provider, private string $serviceType = 'chat') {}
    public function send(ChatRequest $request): ChatResponse {
        $log = ServiceUsageLogDraft::start($this->provider, $this->serviceType, $this->inner::class, $request, model: $request->model);
        try {
            $resp = $this->inner->send($request);
            $log->finishSuccess($resp);
            $log->persist();
            return $resp;
        } catch(\Throwable $e) {
            $log->finishError($e);
            $log->persist();
            throw $e; // preserve behavior
        }
    }
    public function stream(ChatRequest $request, ?Closure $onDelta = null): Generator {
        $log = ServiceUsageLogDraft::start(...);
        $gen = $this->inner->stream($request, function($delta) use ($onDelta, $log) {
            $log->accumulate($delta);
            if ($onDelta) $onDelta($delta);
        });
        try {
            foreach ($gen as $delta) { yield $delta; }
            $log->finishSuccessStreaming();
        } catch(\Throwable $e) { $log->finishError($e); throw $e; }
        finally { $log->persist(); }
    }
}
```

### Draft Helper
`ServiceUsageLogDraft` holds intermediate state; does not hit DB until `persist()`.

### Queue Job
`PersistServiceUsageLogJob` accepts array data. Uses configured connection & queue name. In `handle()` simply `ServiceUsageLog::create($data)` catching errors.

### Service Provider Wiring
During driver creation in each Manager:
```php
$driver = new OpenAIChatDriver($cfg);
return LoggingDriverFactory::wrap('chat','openai',$driver);
```
`LoggingDriverFactory::wrap` checks config toggles and returns either the raw driver or logging decorator.

### Pruning Command
`php artisan ai-usage-logs:prune` → deletes rows older than `now()->subDays(config('uniformed-ai.logging.prune.days'))` if enabled.

## 7. Privacy & Performance Considerations
- Redaction always on when `redact.enabled=true`; mandatory masking of obvious API keys (search for patterns: `sk-[A-Za-z0-9]{20,}`, `^AIza[\w-]{30,}`, generic 32-64 length hex). Replace with configured mask preserving last 4 chars optionally (future enhancement).
- Truncation ensures huge prompts or base64 images do not exceed configured lengths.
- Streaming chunk cap prevents pathological memory use. Chunks optional.
- Async queue mode off by default to avoid forcing queue dependency; recommend enabling in production for high traffic.
- Logging failures trigger `report()` but never bubble up; prevents user-facing errors.

## 8. Testing Strategy
Use Pest.
- Migration test: run migrations, assert table exists (`service_usage_logs`) & columns cast properly.
- Synchronous success log: fake time, perform `chat()->send()`, assert one row with status success, model recorded.
- Error path: have driver throw `ProviderException`, assert log status error, error_class set.
- Streaming: fake driver producing 3 chunks, ensure final content stored, optional chunk array stored when enabled.
- Redaction: inject API key into request DTO, assert stored payload masked.
- Truncation: create oversized prompt, assert truncated suffix present.
- Disabled global logging: ensure no row created.
- Per-service toggle off: only that service unlogged.
- Queue mode: enable queue config, fake bus, assert `PersistServiceUsageLogJob` dispatched with sanitized payload.
- Prune command: seed old + new logs, run command, assert only recent remain.

Factories / Helpers:
- Minimal fake driver implementing contract returning canned response.
- Use `Http::fake()` per existing pattern; though logging is independent of HTTP calls.

## 9. Future Enhancements
- Token usage & cost metrics (columns: prompt_tokens, completion_tokens, total_tokens, cost_cents).
- Correlation ID propagation (request_id) for distributed tracing across services.
- Export / reporting artisan commands (CSV, JSONL).
- Observer integration or events dispatch after persistence for analytics pipelines.
- Log rotation to cold storage (S3) after N days.
- Pluggable storage (e.g., send to OpenSearch / ClickHouse instead of relational DB).
- UI blade components or Telescope integration panel.
- Anonymization rules / field-level encryption for PII.
- Sampling rate config (e.g., only 25% of successful logs to reduce volume).

## 10. Summary
This design introduces an opt-in, safe, extensible Service Usage Logs layer for AI operations with minimal runtime overhead. It centralizes observability, supports privacy via redaction & truncation, and lays groundwork for future analytics (tokens, cost). Implementation uses a base `AbstractLoggingDriver` (reducing duplication), thin decorators around existing drivers, a draft object for minimal allocations, configurable async persistence, and robust test coverage to ensure reliability and extensibility.
