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

## State of the Art Model Pricing (as of Mar 2026)

The following table reflects the default pricing data included in the package.

*   **Prices:** USD per 1 Million tokens.
*   **Tiers:** "Low" rate applies to usage below the threshold (e.g., 128k or 200k tokens), "High" rate applies above it.

| Provider | Model | Input Cost / 1M | Output Cost / 1M | Tiers / Notes |
| :--- | :--- | :--- | :--- | :--- |
| **OpenAI** | `gpt-5.2` | $1.75 | $14.00 | Standard rate |
| **OpenAI** | `gpt-5.2-pro` | $21.00 | $168.00 | High-reasoning model |
| **OpenAI** | `gpt-5.4` | $2.50 / $5.00 | $15.00 / $22.50 | **Tiered:** (≤272k / >272k); [OpenAI pricing](https://developers.openai.com/api/docs/pricing) |
| **OpenAI** | `gpt-5.4-pro` | $30.00 / $60.00 | $180.00 / $270.00 | **Tiered:** (≤272k / >272k); reasoning tokens as output |
| **Anthropic** | `claude-opus-4.6` | $5.00 | $25.00 | [Claude API pricing](https://claude.com/pricing#api); prompt caching available |
| **Anthropic** | `claude-sonnet-4.6` | $3.00 | $15.00 | Same source |
| **Anthropic** | `claude-haiku-4.5` | $1.00 | $5.00 | Same source; fastest, most cost-efficient |
| **Google** | `gemini-3.1-pro-preview` | $2.00 / $4.00 | $12.00 / $18.00 | **Tiered:** (≤200k / >200k); output includes thinking tokens |
| **Google** | `gemini-3.1-pro-preview-customtools` | $2.00 / $4.00 | $12.00 / $18.00 | Same as 3.1 Pro Preview |
| **Google** | `gemini-3.1-flash-lite-preview` | $0.25 | $1.50 | Cost-efficient; text/image/video input (audio $0.50) |
| **Google** | `gemini-3.1-flash-image-preview` | $0.50 | $3.00 | Text/thinking; image output priced separately |
| **Google** | `gemini-3-flash-preview` | $0.50 | $3.00 | High speed, low cost; output includes thinking |
| **DeepSeek** | `deepseek-v3.2` | $0.25 | $0.38 | Extremely competitive pricing |
| **Qwen** | `qwen3-max` | $1.20 / $3.00 | $6.00 / $15.00 | **Tiered:** (≤128k / >128k) |
| **xAI** | `grok-4.20-multi-agent-beta-0309` | $2.00 | $6.00 | 2M context; [xAI models](https://docs.x.ai/developers/models); cached input $0.20 |
| **xAI** | `grok-4.20-beta-0309-reasoning` | $2.00 | $6.00 | Same as 4.20 multi-agent |
| **xAI** | `grok-4.20-beta-0309-non-reasoning` | $2.00 | $6.00 | Same as 4.20, no reasoning |
| **xAI** | `grok-4-fast` | $0.40 | $1.00 | OpenRouter |
| **xAI** | `grok-4.1-fast` | $0.20 | $0.50 | 2M context window |
| **xAI** | `grok-code-fast-1` | $0.20 | $1.50 | 256K context window |

*Note: Claude 4.6 / Haiku 4.5 from [Claude API pricing](https://claude.com/pricing#api). GPT 5.4 from [OpenAI API pricing](https://developers.openai.com/api/docs/pricing). Grok 4.20 from [xAI models & pricing](https://docs.x.ai/developers/models). Google Gemini 3.1 from [Gemini API pricing](https://ai.google.dev/gemini-api/docs/pricing#gemini-3.1-pro-preview) (Mar 2026). Other pricing from OpenRouter/Jan 2026. Gemini 3 Pro Preview was deprecated March 9, 2026; use Gemini 3.1 Pro Preview.*

## Token Usage Reference

For context when estimating costs and usage:

- **~1 page of text content** ≈ 600 tokens
- **100 lines of code** ≈ 1000 tokens

These estimates can help you gauge approximate token consumption for different types of content when planning your AI service usage and budget.
