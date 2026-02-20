# ElevenLabs Image & Video — Implementation Plan

ElevenLabs acts as a gateway API, offering access to multiple best-in-class models (Google Veo, OpenAI Sora, KlingAI, Wan, Seedance, Flux, etc.) through a single unified API key. This plan outlines how to integrate ElevenLabs as a new provider for both `image` and `video` services within this package.

Official docs: https://elevenlabs.io/docs/overview/capabilities/image-video

---

## 1. API Overview & Constraints

- **Base URL**: `https://api.elevenlabs.io` (same as the existing audio driver)
- **Authentication**: `xi-api-key` header (already configured in `config/uniformed-ai.php`)
- **Status**: Image & Video is currently in **beta**. The REST API endpoints follow ElevenLabs' standard versioning (`/v1/image-video/generations`), but exact schemas must be confirmed against the live API or their OpenAPI spec before finalising request payloads.
- **Execution model**: **Asynchronous** — submit a generation request, receive a `generation_id`, then poll `GET /v1/image-video/generations/{id}` until the status is terminal (`completed` or `failed`). This mirrors the KIEImageDriver polling pattern already in the codebase.
- **Output formats**: PNG (image), MP4 H.264/H.265 (video) — delivered as a signed download URL in the completed generation response.
- **Plan requirements**: Video generation requires a paid ElevenLabs plan.

---

## 2. Models Catalogue

### Image Models

| Catalog Key                  | ElevenLabs Model ID (expected)  | Strengths                                      |
|------------------------------|---------------------------------|------------------------------------------------|
| `google-nano-banana`         | `google-nano-banana`            | Fast, high-quality, multi-reference support    |
| `seedream-4`                 | `seedream-4`                    | Dynamic compositions, physics-aware            |
| `flux-1-kontext-pro`         | `flux-1-kontext-pro`            | Style control, scene coherence, reference-only |
| `wan-2.5`                    | `wan-2.5`                       | Motion-aware stills, negative prompt support   |
| `openai-gpt-image-1`         | `openai-gpt-image-1`            | Precise text-guided creation and editing       |

### Video Models

| Catalog Key                  | ElevenLabs Model ID (expected)  | Duration Options     | Resolutions    |
|------------------------------|---------------------------------|----------------------|----------------|
| `sora-2-pro`                 | `sora-2-pro`                    | 4s, 8s, 12s          | 720p, 1080p    |
| `sora-2`                     | `sora-2`                        | 4s, 8s, 12s          | 720p, 1080p    |
| `google-veo-3.1`             | `google-veo-3.1`                | 4s, 6s, 8s           | 720p, 1080p    |
| `google-veo-3.1-fast`        | `google-veo-3.1-fast`           | 4s, 6s, 8s           | 720p, 1080p    |
| `google-veo-3`               | `google-veo-3`                  | 4s, 6s, 8s           | 720p, 1080p    |
| `google-veo-3-fast`          | `google-veo-3-fast`             | 4s, 6s, 8s           | 720p, 1080p    |
| `kling-2.5`                  | `kling-2.5`                     | 5s, 10s              | 1080p          |
| `seedance-1-pro`             | `seedance-1-pro`                | 3s–12s               | 480p–1080p     |
| `wan-2.5`                    | `wan-2.5`                       | 5s, 10s              | 480p–1080p     |

### Post-processing Models (future scope, not in this phase)

- **Topaz Upscale** — video/image upscaling up to 4×
- **Omnihuman 1.5** — image-to-talking-video lip-sync
- **Veed LipSync** — video-to-video lip-sync

---

## 3. Files to Create

### 3.1 `src/Services/Image/Providers/ElevenLabsImageDriver.php`

Implements `ImageContract` (`create`, `modify`, `upscale`).

**`create(ImageRequest $request): ImageResponse`**
- POST `v1/image-video/generations` with JSON body:
  - `type`: `"image"`
  - `model_id`: from `$request->model` or config default
  - `prompt`: `$request->prompt`
  - `aspect_ratio`: derived from `$request->size` (e.g. `"1:1"`, `"16:9"`)
  - `num_images`: from `$request->options['num_images'] ?? 1` (max 4)
  - Optional: `reference_image_urls` from `$request->options['reference_image_urls']`
  - Optional: `negative_prompt` from `$request->options['negative_prompt']`
- Extract `generation_id` from response
- Poll `GET v1/image-video/generations/{generation_id}` until `status` is `completed` or `failed`
- On completion, extract `outputs[].url` array
- Return `new ImageResponse(images: [['url' => ...]], raw: $final)`

**`modify(ImageRequest $request): ImageResponse`**
- Same as `create()`, but passes `$request->imagePath` as a reference image URL in `reference_image_urls`; model should default to `flux-1-kontext-pro` (designed for image editing)

**`upscale(ImageRequest $request): ImageResponse`**
- POST `v1/image-video/generations` with:
  - `type`: `"upscale"`
  - `model_id`: `"topaz-upscale"`
  - `source_url`: `$request->options['source_url']` (required)
  - `scale`: `$request->options['scale'] ?? 2` (1×, 1.25×, 1.5×, 1.75×, 2×, 3×, 4×)
- Poll and return image URL

**Polling helper** (private method, mirrors `KIEImageDriver::pollUntilComplete`):
```
interval: options['poll']['interval'] ?? 3  (seconds)
timeout:  options['poll']['timeout']  ?? 300 (seconds)
terminal states: completed, failed
```

**HTTP client**: `HttpClientFactory::make($this->cfg, 'elevenlabs')->withHeaders(['xi-api-key' => $this->cfg['api_key']])`

---

### 3.2 `src/Services/Video/Providers/ElevenLabsVideoDriver.php`

Implements `VideoContract` (`generate`, `edit`).

**`generate(VideoRequest $request): VideoResponse`**
- POST `v1/image-video/generations` with JSON body:
  - `type`: `"video"`
  - `model_id`: from `$request->model` or config default
  - `prompt`: `$request->prompt`
  - `duration_seconds`: `$request->durationSeconds` (must match model's allowed durations)
  - `aspect_ratio`: from `$request->options['aspect_ratio'] ?? '16:9'`
  - `resolution`: from `$request->options['resolution'] ?? '1080p'`
  - Optional `start_frame_url`, `end_frame_url`, `reference_image_urls` from `$request->options`
  - Optional `negative_prompt`, `enable_audio` from `$request->options`
- Poll until `status === 'completed'` or `'failed'`
- On completion, return `new VideoResponse(url: $output['url'], format: 'mp4', raw: $final)`

**`edit(VideoRequest $request): VideoResponse`**
- Delegate to `generate()` — editing is prompt-driven refinement; caller provides a `start_frame_url` in `$request->options` pointing to the existing video frame

**Polling**: Same pattern as the image driver, with longer defaults (interval 5s, timeout 900s) given video generation times.

---

### 3.3 `src/Logging/Decorators/LoggingVideoDriver.php`

Video is the only service that does not yet have a logging decorator (all others — chat, image, audio, music, search — already have one). This must be created before the ElevenLabs video driver can be wrapped.

Mirrors `LoggingImageDriver` exactly, adapted for `VideoContract`:
- Wraps `generate()` and `edit()`
- Records provider, service (`video`), model, prompt length, and elapsed time in `LogDraft`
- Follows the same `LoggingDriverFactory::wrap('video', $provider, $driver)` registration pattern

---

## 4. Files to Modify

### 4.1 `src/Services/Image/ImageManager.php`

Add factory method:

```php
protected function createElevenlabsDriver(): ImageContract
{
    return LoggingDriverFactory::wrap('image', 'elevenlabs', new ElevenLabsImageDriver(config('uniformed-ai.providers.elevenlabs')));
}
```

Add import for `ElevenLabsImageDriver`.

---

### 4.2 `src/Services/Video/VideoManager.php`

Add factory method:

```php
protected function createElevenlabsDriver(): VideoContract
{
    return LoggingDriverFactory::wrap('video', 'elevenlabs', new ElevenLabsVideoDriver(config('uniformed-ai.providers.elevenlabs')));
}
```

Add import for `ElevenLabsVideoDriver`.

---

### 4.3 `src/Support/ServiceCatalog.php`

Add `elevenlabs` under both `image` and `video`:

```php
'image' => [
    'openai'      => ['gpt-image-1'],
    'kie'         => ['mj', '4o'],
    'replicate'   => ['google/nano-banana', ...],
    'elevenlabs'  => [
        'google-nano-banana',
        'seedream-4',
        'flux-1-kontext-pro',
        'wan-2.5',
        'openai-gpt-image-1',
    ],
],

'video' => [
    'replicate'  => [...],
    'kie'        => ['veo3'],
    'elevenlabs' => [
        'sora-2-pro',
        'sora-2',
        'google-veo-3.1',
        'google-veo-3.1-fast',
        'google-veo-3',
        'google-veo-3-fast',
        'kling-2.5',
        'seedance-1-pro',
        'wan-2.5',
    ],
],
```

---

### 4.4 `config/uniformed-ai.php`

The `providers.elevenlabs` key already exists with `api_key`, `base_url`, `voice_id`, and `model`. Extend it with image/video defaults:

```php
'elevenlabs' => [
    'api_key'       => env('ELEVENLABS_API_KEY'),
    'base_url'      => env('ELEVENLABS_BASE_URL', 'https://api.elevenlabs.io'),
    'voice_id'      => env('ELEVENLABS_VOICE_ID', 'Rachel'),
    'model'         => env('ELEVENLABS_MODEL', 'eleven_multilingual_v2'),
    'image_model'   => env('ELEVENLABS_IMAGE_MODEL', 'google-nano-banana'),
    'video_model'   => env('ELEVENLABS_VIDEO_MODEL', 'google-veo-3-fast'),
],
```

Optionally update `defaults.image` and `defaults.video` env variable documentation comments to include `elevenlabs` as an option.

---

### 4.5 `src/Logging/LoggingDriverFactory.php`

Register the new `LoggingVideoDriver` so that `LoggingDriverFactory::wrap('video', ...)` works. Currently only chat, image, audio, music, and search are handled. Add a `video` case mirroring the image case.

---

## 5. Implementation Order

The steps have a dependency chain — complete them in order:

1. **`LoggingVideoDriver`** — required before video drivers can be wrapped with logging
2. **`LoggingDriverFactory`** — register the `video` case
3. **`ElevenLabsImageDriver`** — image generation, async polling
4. **`ElevenLabsVideoDriver`** — video generation, async polling
5. **`ImageManager`** — add `createElevenlabsDriver()`
6. **`VideoManager`** — add `createElevenlabsDriver()`
7. **`ServiceCatalog`** — add elevenlabs entries for image and video
8. **`config/uniformed-ai.php`** — add `image_model` and `video_model` keys

---

## 6. Key Design Decisions

**Shared config block**: Both image and video drivers reuse the existing `providers.elevenlabs` config key, adding only model-specific defaults. This avoids duplicating API key and base URL.

**Async polling encapsulated in the driver**: Callers use the same synchronous contract interface (`create()`, `generate()`). Polling is an internal implementation detail, with configurable `options['poll']['interval']` and `options['poll']['timeout']` for callers that need to tune behaviour.

**`edit()` delegates to `generate()`**: The `VideoContract::edit` method is fulfilled by passing the source content as a `start_frame_url` option — ElevenLabs' generation endpoint handles both text-to-video and image/frame-conditioned video in the same request.

**No new DTOs required**: `ImageRequest`/`ImageResponse` and `VideoRequest`/`VideoResponse` have sufficient flexibility through their `options: array` field to accommodate ElevenLabs-specific parameters (aspect ratio, resolution, reference images, audio toggle, etc.).

**Model ID passthrough**: The drivers forward `$request->model` directly to `model_id` in the API payload. Model identifiers in `ServiceCatalog` must exactly match ElevenLabs' expected model ID strings — these should be verified against the live API or their SDK once beta access is confirmed.

---

## 7. Open Questions (to resolve before coding)

1. **Exact REST endpoint paths**: The docs do not yet publish a public OpenAPI spec for the Image & Video beta. Endpoints assumed here (`POST /v1/image-video/generations`, `GET /v1/image-video/generations/{id}`) must be confirmed once beta access is granted.
2. **Model ID strings**: The exact strings ElevenLabs expects for `model_id` (e.g. `google-veo-3.1` vs `veo-3.1` vs `google_veo_3_1`) must be verified.
3. **Reference image handling**: Whether reference images are passed as URLs or multipart file uploads in the request body.
4. **Credit/quota errors**: The error shape for credit exhaustion or plan-gating needs to be mapped to appropriate `ProviderException` subtypes.
5. **Upscale for video**: The `Topaz Upscale` model supports video upscaling — `VideoContract` currently has no `upscale()` method. This may warrant a future contract extension or a dedicated post-processing service.
