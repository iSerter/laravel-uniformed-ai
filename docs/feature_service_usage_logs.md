# Feature Design: AI Usage Logs

## 1. Overview / Goal
Provide an optional, configurable logging facility that records every AI service interaction (Chat, Image, Audio, Music, Search) including request metadata, response payloads (sanitized), timing, outcome status, and (when available) the authenticated user id. Service Usage Logs enable debugging, auditing, analytics (future token/cost tracking), and observability without leaking secrets or causing material latency.

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
- provider (string 40) index
- service_type (string 20) index
- driver (string 120)
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
This design introduces an opt-in, safe, extensible Service Usage Logs layer for AI operations with minimal runtime overhead. It centralizes observability, supports privacy via redaction & truncation, and lays groundwork for future analytics (tokens, cost). Implementation uses decorators around existing drivers, a draft object for minimal allocations, configurable async persistence, and robust test coverage to ensure reliability.
