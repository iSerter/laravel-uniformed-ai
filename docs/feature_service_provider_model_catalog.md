# Feature Plan: Service Provider & Model Catalog API

## Summary
Add lightweight introspection methods to list supported providers and their available (curated) model identifiers for each AI service (chat, image, audio, music, search). This enables UIs / admin panels / validation layers to present selectable options without hard‑coding them application‑side. Initial implementation will be static (hardcoded curated lists) and can later evolve to dynamic discovery (e.g., via provider APIs or config overrides).

## Goals
1. Provide `getProviders()` and `getModels($provider)` on each service manager (Chat, Image, Audio, Music, Search).
2. Provide a facade‑level aggregate method to retrieve all services with their providers and models in one structure (`AI::catalog()`).
3. Keep implementation simple & zero‑network: curated static arrays of prominent providers/models.
4. Allow future extension via config or runtime registration without breaking the public API.
5. Ensure calling unknown provider returns empty array (or optionally throws) in a predictable way.

## Non‑Goals (Phase 1)
- Dynamic real‑time model fetching from provider APIs.
- Returning pricing, context windows, token limits (could be Phase 2).
- Filtering by capability (vision, tool calling, json‑mode). (Reserve for future.)

## Public API Additions

### Per Manager
```php
/** @return string[] */
public function getProviders(): array;

/** @return string[] List of model identifiers (curated) */
public function getModels(string $provider): array;
```

### Facade Aggregate
```php
/**
 * Returns associative array keyed by service name.
 * [
 *   'chat' => [ 'openai' => ['gpt-4.1-mini','gpt-4.1','o3-mini'], 'openrouter' => [...], ... ],
 *   'image' => [ 'openai' => ['gpt-image-1'], ... ],
 *   'audio' => [ 'elevenlabs' => ['eleven_multilingual_v2'], ... ],
 *   'music' => [ 'piapi' => ['music/default'], ... ],
 *   'search' => [ 'tavily' => ['tavily/advanced'], ... ],
 * ]
 */
public static function catalog(): array;
```

## Naming
The canonical method name is `catalog()` for clarity and consistency with `getModels()` / `getProviders()`.

## Data Source Strategy
Phase 1: Hardcode curated arrays inside a new internal class `Iserter\UniformedAI\Support\ServiceCatalog` to centralize definitions and avoid duplication across managers.

Example skeleton:
```php
class ServiceCatalog
{
    /**
     * Hierarchical map: service => provider => models[]
     */
    public const MAP = [
        'chat' => [
            'openai' => ['gpt-4.1-mini','gpt-4.1','gpt-4o-mini','o3-mini'],
            'openrouter' => ['openrouter/auto','anthropic/claude-3.5-sonnet','meta/llama-3.1-70b-instruct'],
            'google' => ['gemini-1.5-pro','gemini-1.5-flash','gemini-exp-1206'],
            'kie' => ['kie/chat-standard'],
            'piapi' => ['piapi/chat-general'],
        ],
        'image' => [
            'openai' => ['gpt-image-1'],
        ],
        'audio' => [
            'elevenlabs' => ['eleven_multilingual_v2'],
        ],
        'music' => [
            'piapi' => ['music/default','music/v2-beta'],
        ],
        'search' => [
            'tavily' => ['tavily/advanced','tavily/basic'],
        ],
    ];
}
```

Managers will reference `ServiceCatalog::MAP[<service>] ?? []`.

## Manager Changes
Each Manager (e.g., `ChatManager`) implements the two new methods:
```php
public function getProviders(): array
{
    return array_keys(ServiceCatalog::MAP['chat']);
}

public function getModels(string $provider): array
{
    return ServiceCatalog::MAP['chat'][$provider] ?? [];
}
```

No interface change required unless we want to expose these in service contracts (not strictly necessary, but optionally we can extend each Contract). Proposed: keep Contracts focused on operational methods (`send`, `stream`, etc.) and treat catalog functions as manager conveniences.

## Facade Changes
Add static passthrough method on the `AI` facade that delegates to the internal helper:
```php
public static function catalog(): array
{
    return \Iserter\UniformedAI\Support\ServiceCatalog::MAP;
}
```

## Example Usage
```php
// List chat providers
$providers = AI::chat()->getProviders();

// List models for OpenAI chat
$models = AI::chat()->getModels('openai');

// Build select options for a UI
foreach (AI::catalog()['chat'] as $provider => $models) {
    // ... render provider group + model options
}

// Safe fallback if provider missing
$unknownModels = AI::chat()->getModels('nonexistent'); // []
```

## Error / Edge Handling
| Case | Behavior (Phase 1) |
|------|--------------------|
| Unknown service key in catalog (internal misuse) | Return empty array where applicable |
| Unknown provider passed to `getModels` | Return empty array (no exception) |
| Future dynamic expansion duplicates existing model | Keep order; de-duplicate via `array_values(array_unique(...))` if necessary |

Rationale: returning empty arrays simplifies consumer code (can show disabled state); throwing exceptions reserved for operational errors.

## Extensibility (Phase 2+ Ideas)
- Config override: allow users to append custom models via `config('uniformed-ai.catalog_overrides')` merged onto static map.
- Capability metadata: annotate models with features (tool_calling, vision, json_mode, streaming, max_context_tokens) enabling smarter UI filters.
- Pricing + token cost integration (ties into existing PricingEngine + ServicePricing model).
- Dynamic fetch: Methods to refresh provider lists by calling provider endpoints (with caching & TTL).
- Versioning: Add `catalog_version` constant for cache invalidation by apps.

## Implementation Steps
1. Create `Support/ServiceCatalog.php` with constant map.
2. Add `getProviders` / `getModels` methods to each Manager (Chat, Image, Audio, Music, Search).
3. Add facade static method `catalog()`.
4. Add tests:
   - `tests/Unit/ServiceCatalogTest.php` verifying structure & representative entries.
   - Manager method tests (e.g., chat manager returns expected providers/models; unknown provider -> empty array).
5. Update README usage examples (small snippet under a new "Catalog / Introspection" section).
6. (Optional) Document in existing docs index.

## Testing Approach
Minimal since static data:
- Assert that `AI::catalog()` has required top-level service keys.
- Assert curated provider + model presence (e.g., `gpt-4.1-mini`, `eleven_multilingual_v2`).
- Assert unknown provider returns empty array.
- Snapshot (JSON encode) of catalog to detect accidental breaking changes (optional).

## Risks & Mitigations
| Risk | Mitigation |
|------|------------|
| Model list drifts from provider reality | Clear doc comment that list is curated; future dynamic enhancement planned |
| Breaking change if we move from static to dynamic | Maintain existing methods; dynamic layer merges on top |
| Bloat of model list | Keep curated, limit to broadly useful / stable models |

## Open Questions
1. Should contracts expose these methods? (Leaning no for now.)
2. Provide human-friendly labels vs raw IDs? (Future metadata map.)
3. Should empty unknown provider result log a debug message? (Optional.)

## Acceptance Criteria
- Calling `AI::chat()->getProviders()` returns non-empty array including `'openai'`.
- Calling `AI::chat()->getModels('openai')` returns array containing `'gpt-4.1-mini'`.
- Calling `AI::catalog()` returns associative array with keys: `chat,image,audio,music,search`.
- Unknown provider returns `[]` and does not throw.
- New tests pass.

---
Prepared: 2025-10-07
