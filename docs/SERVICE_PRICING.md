# Service Pricing & Usage Tracking

## Feature Overview

The `laravel-uniformed-ai` package includes a robust usage tracking and cost calculation engine, designed to handle the complexity of modern AI model pricing structures.

### Key Capabilities

1.  **Usage Metrics:** Automatically captures token usage (prompt + completion) for supported providers (OpenAI, Anthropic, Google, etc.).
2.  **Cost Calculation:** Calculates estimated costs based on a local pricing database stored in your application.
3.  **Dynamic Pricing (Tiers):** Supports volume-based tiered pricing. Some models (like Claude Sonnet 4.5 or Gemini 1.5 Pro) charge different rates depending on whether the prompt size falls within a certain "context window" (e.g., < 128k tokens vs > 128k tokens).
4.  **Fallback Estimation:** Estimates token usage for providers that don't return usage metadata (using heuristic tokenizers).

### Configuration

Pricing is stored in the database (`service_pricings` and `service_pricing_tiers` tables).

The system resolves pricing in this order:
1.  **Exact match:** (Provider + Service Type + Model Name)
2.  **Global match:** (Provider + Model Name)
3.  **Wildcard match:** (Provider + Model prefix*)

## State of the Art Model Pricing (as of Jan 2026)

The following table reflects the default pricing data included in the package.

*   **Prices:** USD per 1 Million tokens.
*   **Tiers:** "Low" rate applies to usage below the threshold (e.g., 128k or 200k tokens), "High" rate applies above it.

| Provider | Model | Input Cost / 1M | Output Cost / 1M | Tiers / Notes |
| :--- | :--- | :--- | :--- | :--- |
| **OpenAI** | `gpt-5.2` | $1.75 | $14.00 | Standard rate |
| **OpenAI** | `gpt-5.2-pro` | $21.00 | $168.00 | High-reasoning model |
| **Anthropic** | `claude-sonnet-4.5` | $3.00 / $6.00 | $15.00 / $22.50 | **Tiered:** (≤200k / >200k) |
| **Anthropic** | `claude-opus-4.5` | $5.00 | $25.00 | |
| **Anthropic** | `claude-haiku-4.5` | $1.00 | $5.00 | |
| **Google** | `gemini-3-pro-preview` | $2.00 / $4.00 | $12.00 / $18.00 | **Tiered:** (≤200k / >200k) |
| **Google** | `gemini-3-flash-preview` | $0.50 | $3.00 | High speed, low cost |
| **DeepSeek** | `deepseek-v3.2` | $0.25 | $0.38 | Extremely competitive pricing |
| **Qwen** | `qwen3-max` | $1.20 / $3.00 | $6.00 / $15.00 | **Tiered:** (≤128k / >128k) |
| **xAI** | `grok-4-fast` | $0.40 | $1.00 | |

*Note: Pricing is effective as of January 9, 2026, and is sourced primarily from OpenRouter aggregation.*
