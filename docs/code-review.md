# Code Review: Laravel Uniformed AI

## Executive Summary

The library demonstrates a solid architectural foundation, effectively utilizing Laravel's Manager/Driver pattern to abstract various AI providers. The separation of concerns between the service logic (`ChatManager`, `Drivers`) and the logging/observability layer (`LoggingDriverFactory`, `LogDraft`) is excellent. The configuration is comprehensive and follows Laravel conventions.

However, there are a few critical and major issues regarding data integrity, flexibility, and scalability that should be addressed before a stable release.

## 1. Critical Issues

### 1.1. Destructive JSON Truncation in Logging
**Location:** `src/Logging/SanitizesPayloads.php`

The truncation logic attempts to truncate the *serialized* JSON string and then decode it back.

```php
// src/Logging/SanitizesPayloads.php
$encoded = json_encode($arr, ...);
if ($encoded !== false && strlen($encoded) > $maxChars) {
    $truncated = substr($encoded, 0, $maxChars - 15) . '...(truncated)';
    $decoded = json_decode($truncated, true); // <--- This will almost always fail
    if (is_array($decoded)) return $decoded;
    return ['data' => substr($encoded, 0, $maxChars - 15) . '...(truncated)'];
}
```

**Impact:** `json_decode` on a substring of JSON will fail (return `null`) unless the cut happens perfectly between elements at the top level, which is statistically unlikely. The fallback stores the truncated string in a `data` key, destroying the original structure of the log. You lose the ability to query specific fields (like `model` or `usage`) from the logs if the payload is large.

**Recommendation:** Implement a recursive truncation that shortens *individual string values* within the array (e.g., long prompts or base64 images) until the total size is within limits, rather than hacking the serialized string.

## 2. Major Improvements

### 2.1. Lack of Flexibility in DTOs
**Location:** `src/Services/Chat/DTOs/ChatRequest.php`

The `ChatRequest` DTO validates and restricts inputs strictly.

```php
public function __construct(
    public array $messages,
    public ?string $model = null,
    // ...
)
```

**Impact:** AI providers frequently add new parameters (e.g., `seed`, `logit_bias`, `response_format`, `top_k`, `frequency_penalty`). Currently, a user cannot use these features without waiting for a library update.

**Recommendation:** Add an `array $options = []` property to the DTO and merge it into the payload in the drivers.

```php
// In Driver
$payload = array_merge([
    'model' => ...,
    // ... standard fields
], $request->options);
```

### 2.2. Auth Responsibility Leakage
**Location:** `src/Support/HttpClientFactory.php`

The factory abstracts authentication for most providers (Bearer tokens) but explicitly leaves Google and Tavily to the drivers:

```php
case 'google': // handled via query param at call site
case 'tavily': // handled via JSON body at call site
    // Intentionally skip Authorization header
    break;
```

**Impact:** This violates the Factory pattern's goal of centralizing configuration. If a developer implements a custom `GoogleChatDriver` or overrides it, they must remember to manually append the API key.

**Recommendation:** Handle this in the factory using `withQueryParameters` or `withOptions` (for body injection via middleware, though query params are easier to handle here).

### 2.3. Scalability of Pruning
**Location:** `src/Logging/Commands/PruneServiceUsageLogs.php`

The command performs a hard delete on all matching rows:

```php
$count = $model->newQuery()->where('created_at', '<', $cutoff)->delete();
```

**Impact:** On high-volume applications (millions of logs), this query can lock the database table for a significant time or time out.

**Recommendation:** Chunk the deletion using `limit` in a loop.

```php
do {
    $deleted = $model->newQuery()->where('created_at', '<', $cutoff)->limit(1000)->delete();
} while ($deleted > 0);
```

### 2.4. Storage Bloat from Stream Chunks
**Location:** `src/Logging/LogDraft.php` & `config/uniformed-ai.php`

The default configuration has `'store_chunks' => true`.

**Impact:** Storing every SSE delta chunk (which can be hundreds per request) significantly bloats the database. For a 1000-token response, you might store 500-1000 array entries in the JSON column.

**Recommendation:** Change the default to `false`. Most users only care about the final aggregated text.

## 3. Minor Observations

*   **Banker's Rounding:** The implementation in `PricingEngine::bankersRound` is a manual implementation (`$floor % 2 === 0 ...`). While logically close, `round($val, 0, PHP_ROUND_HALF_EVEN)` is the native PHP way to do this.
*   **Performance:** `SanitizesPayloads` runs a recursive loop and executes `preg_match` (with multiple patterns) on *every* string value in the payload. For large context windows (128k tokens), this could add measurable latency. Consider checking keys first or optimizing the regex to fail faster.
*   **Dependency Injection:** `PricingEngine` is instantiated with `new PricingEngine(...)` inside the ServiceProvider, which is fine, but ensuring `PricingRepository` is also bound/resolved via the container allows for easier swapping.

## 4. Security

*   **Sanitization:** The regex patterns for secrets seem robust enough for standard keys (sk-..., etc.).
*   **Redaction:** Ensure that `redactKeys` includes variations like `api-key` (dashes) if array keys are normalized, though the current check `in_array(strtolower((string) $key)...)` handles case insensitivity well.

## 5. Conclusion

The library is well on its way to being a robust tool. Fixing the JSON truncation and opening up the DTOs for extra parameters are the most immediate priorities to ensure usability and data safety.
