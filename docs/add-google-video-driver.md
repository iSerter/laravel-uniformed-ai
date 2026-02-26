# Add Google (Gemini Veo) Video Driver — Implementation Plan

This plan adds a **Google** video provider that uses the [Gemini API’s Veo video generation](https://ai.google.dev/gemini-api/docs/video). Veo generates high-fidelity video (with optional natively generated audio) via a long-running operation: submit a job, then poll until complete and return the video URL.

**Reference:** [Generate videos with Veo 3.1 in Gemini API](https://ai.google.dev/gemini-api/docs/video)

---

## 1. API Overview

- **Base URL**: `https://generativelanguage.googleapis.com` (existing `config('uniformed-ai.providers.google')` already has `base_url` and `api_key`).
- **API version**: Veo is exposed under the **v1beta** Gemini API.
- **Authentication**: [Gemini Video REST examples](https://ai.google.dev/gemini-api/docs/video) use the **`x-goog-api-key`** header. The package’s `HttpClientFactory` does not add auth for the `google` provider (key is usually added at call site). The new driver should send **`x-goog-api-key: {api_key}`** on every request (same pattern as ElevenLabs’ `xi-api-key` in its driver).
- **Execution model**: **Asynchronous**.  
  - **Start**: `POST v1beta/models/{model}:predictLongRunning` with body `instances` + `parameters`.  
  - **Poll**: `GET v1beta/{operation.name}` until `done === true`.  
  - **Result**: `response.generateVideoResponse.generatedSamples[0].video.uri` is the download URL. The download request to that URI also requires the API key (header or query).
- **Output**: 8s (or 4s/6s for some configs), 720p / 1080p / 4k, MP4, 24fps. Video retention is 2 days on Google’s side.

---

## 2. Supported Models (catalog)

| Catalog ID                     | Gemini API model name                  | Notes                                      |
|--------------------------------|----------------------------------------|--------------------------------------------|
| `veo-3.1-generate-preview`     | `veo-3.1-generate-preview`             | Veo 3.1 Preview, 4/6/8s, refs, extension  |
| `veo-3.1-fast-generate-preview`| `veo-3.1-fast-generate-preview`        | Veo 3.1 Fast Preview                       |
| `veo-2.0-generate-001`         | `veo-2.0-generate-001`                 | Veo 2, 5–8s, no extension/refs             |

Default for the driver: **`veo-3.1-generate-preview`** (or configurable via `config('uniformed-ai.providers.google.video_model')`).

---

## 3. Request Shape (REST)

- **Endpoint**: `POST v1beta/models/{model}:predictLongRunning`
- **Headers**: `Content-Type: application/json`, `x-goog-api-key: {api_key}`

**Body:**

- **`instances`**: array of one object:
  - **`prompt`** (string): required.
  - **`image`** (optional): `{ "inlineData": { "mimeType": "image/png", "data": "<base64>" } }` for image-to-video (first frame).
  - **`video`** (optional): `{ "inlineData": { "mimeType": "video/mp4", "data": "<base64>" } }` for video extension (Veo 3.1 only).
- **`parameters`** (optional):
  - **`aspectRatio`**: `"16:9"` (default) or `"9:16"`.
  - **`resolution`**: `"720p"` (default), `"1080p"`, `"4k"` (1080p/4k only with 8s and certain features).
  - **`durationSeconds`**: Veo 3.1: `"4"`, `"6"`, `"8"`; must be `"8"` for 1080p/4k or when using reference images/extension.
  - **`negativePrompt`**: string.
  - **`lastFrame`** (Veo 3.1): same structure as `image` for first/last frame interpolation.
  - **`referenceImages`** (Veo 3.1): array of `{ "image": { "inlineData": {...} }, "referenceType": "asset" }` (up to 3).
  - **`numberOfVideos`**: e.g. `1` (extension flows use this).
  - **`personGeneration`**: region-dependent; e.g. `"allow_all"` or `"allow_adult"`.

The driver should map `VideoRequest` + `options` into this structure (see below).

---

## 4. Polling and Result

- **Poll**: `GET v1beta/{operation_name}` with same `x-goog-api-key` header.  
  - `operation_name` is the `name` field from the initial POST response (e.g. `operations/...`).
- **Terminal state**: `done === true`.
- **On success**:  
  `response.generateVideoResponse.generatedSamples[0].video.uri` is the download URL.  
  Downloading that URI requires the API key (REST examples use `x-goog-api-key` on the GET request).
- **On error**: If `done === true` and `error` is set, throw a `ProviderException` with the error payload.
- **Timeout**: Implement a configurable polling timeout (e.g. 10–15 minutes) and interval (e.g. 10s), consistent with existing async drivers (e.g. ElevenLabs video).

---

## 5. Files to Add

### 5.1 `src/Services/Video/Providers/GoogleVideoDriver.php`

Implements `VideoContract` (`generate`, `edit`).

**Constructor**  
- Accept `array $cfg` (same as other drivers). Use `config('uniformed-ai.providers.google')` when registering the driver so it shares the existing Google API key and base URL.

**`generate(VideoRequest $request): VideoResponse`**

1. Build HTTP client: `HttpClientFactory::make($this->cfg, 'google')` and add header `x-goog-api-key: $this->cfg['api_key']`.
2. Model: `$request->model ?? $this->cfg['video_model'] ?? 'veo-3.1-generate-preview'`.
3. Build body:
   - `instances`: `[ [ 'prompt' => $request->prompt ] ]`. If `$request->options['image_inline_data']` is set (e.g. base64 + mimeType), add `image: { inlineData: ... }` to the instance. If `$request->options['video_inline_data']` is set (for extension), add `video: { inlineData: ... }`.
   - `parameters`: from `$request->options` map into API parameters:
     - `aspect_ratio` → `aspectRatio` (`16:9` / `9:16`),
     - `resolution` → `resolution` (`720p`, `1080p`, `4k`),
     - `duration_seconds` → `durationSeconds` (string `"4"` / `"6"` / `"8"`),
     - `negative_prompt` → `negativePrompt`,
     - `last_frame_inline_data` → `lastFrame`,
     - `reference_images` → `referenceImages` (array of `{ image: { inlineData }, referenceType: "asset" }`),
     - `number_of_videos` → `numberOfVideos`,
     - `person_generation` → `personGeneration`.
4. POST to `v1beta/models/{model}:predictLongRunning`.
5. On non-2xx: throw `ProviderException` with status and body.
6. Read `name` from response (operation name). If missing, throw.
7. Poll `GET v1beta/{name}` (with same auth) until `done === true`, with configurable interval and timeout (e.g. from `$request->options['poll']`).
8. If final response has `error`, throw `ProviderException`.
9. Extract `uri` from `response.generateVideoResponse.generatedSamples[0].video.uri`. If missing, throw.
10. Return `new VideoResponse(b64Video: null, url: $uri, format: 'mp4', raw: $finalResponse)`.  
    **Note:** The URI may require the API key for download; document that callers should use the same key when fetching the URL (e.g. add `x-goog-api-key` or the query param the download endpoint expects).

**`edit(VideoRequest $request): VideoResponse`**

- **Option A (recommended):** Delegate to `generate($request)`. “Edit” is expressed via options: e.g. `image_inline_data` (start frame), `last_frame_inline_data` (end frame), or `video_inline_data` (extension). No separate edit endpoint in the Veo API.
- **Option B:** If we later define edit as “always image-to-video”, we could set a default for first frame from options; for now, Option A keeps the contract simple.

**Helpers (private)**  
- `buildInstances(VideoRequest $request): array`  
- `buildParameters(array $options): array`  
- `pollUntilDone(PendingRequest $http, string $operationName, array $pollOptions): array`  
- `extractVideoUri(array $response): ?string`  
- Null-safe use of `$request->options` (e.g. `$request->options ?? []`) so nullable options never cause errors.

**Error handling**  
- Non-2xx on POST or GET: throw `ProviderException` with provider `'google'`.  
- Poll timeout: throw with a clear message and 504-style status.  
- `done === true` with `error`: throw with backend error details.  
- Missing `uri` in final response: throw with 502-style message.

---

## 6. Files to Modify

### 6.1 `src/Services/Video/VideoManager.php`

- Add: `use Iserter\UniformedAI\Services\Video\Providers\GoogleVideoDriver;`
- Add factory:

```php
protected function createGoogleDriver(): VideoContract
{
    return LoggingDriverFactory::wrap('video', 'google', new GoogleVideoDriver(config('uniformed-ai.providers.google')));
}
```

Laravel’s Manager will resolve `driver('google')` to `createGoogleDriver()`.

### 6.2 `src/Support/ServiceCatalog.php`

Under `'video'`, add a `'google'` entry with the Veo model IDs used in the API:

```php
'video' => [
    'replicate' => [...],
    'kie' => ['veo3'],
    'elevenlabs' => [...],
    'google' => ['veo-3.1-generate-preview', 'veo-3.1-fast-generate-preview', 'veo-2.0-generate-001'],
],
```

### 6.3 `config/uniformed-ai.php`

- Under `providers.google`, add an optional default for video:

```php
'google' => [
    'api_key'  => env('GOOGLE_AI_API_KEY'),
    'base_url' => env('GOOGLE_AI_BASE_URL', 'https://generativelanguage.googleapis.com'),
    'chat'     => ['model' => env('GOOGLE_CHAT_MODEL', 'gemini-1.5-pro')],
    'video_model' => env('GOOGLE_VIDEO_MODEL', 'veo-3.1-generate-preview'),
],
```

- Optionally under the existing `video` overrides (if you keep that structure), add:

```php
'video' => [
    ...
    'google' => ['model' => env('GOOGLE_VIDEO_MODEL', 'veo-3.1-generate-preview')],
],
```

Use one place consistently; the driver should read `$this->cfg['video_model'] ?? 'veo-3.1-generate-preview'` so that either the top-level `video_model` or a nested `video.model` (if merged) works.

---

## 7. VideoRequest Options Mapping (for callers)

Document for users of the package which `VideoRequest::$options` keys the Google driver understands (and pass-through to Gemini):

| Option key                  | Gemini parameter   | Type / notes                                                                 |
|----------------------------|--------------------|-------------------------------------------------------------------------------|
| `aspect_ratio`             | `aspectRatio`      | `"16:9"` \| `"9:16"`                                                          |
| `resolution`               | `resolution`       | `"720p"` \| `"1080p"` \| `"4k"`                                              |
| `duration_seconds`         | `durationSeconds`  | `4` \| `6` \| `8` (sent as string in JSON)                                    |
| `negative_prompt`          | `negativePrompt`   | string                                                                        |
| `image_inline_data`        | (instance)         | `{ mimeType, data }` for first-frame image-to-video                           |
| `video_inline_data`        | (instance)         | `{ mimeType, data }` for video extension (Veo 3.1)                            |
| `last_frame_inline_data`   | `lastFrame`        | `{ inlineData: { mimeType, data } }` (Veo 3.1 interpolation)                 |
| `reference_images`         | `referenceImages`  | array of `{ image: { inlineData }, referenceType: "asset" }` (up to 3, Veo 3.1) |
| `person_generation`        | `personGeneration` | e.g. `"allow_all"` \| `"allow_adult"` (region-dependent)                      |
| `poll`                     | —                  | `[ 'interval' => 10, 'timeout' => 900 ]` for polling (seconds)                |

`duration_seconds` from `VideoRequest` can be used as the primary input and overridden by `options['duration_seconds']` if present.

---

## 8. Implementation Order

1. **Config**: Add `video_model` (and optional `video.google.model`) so the driver has a default.
2. **GoogleVideoDriver**: Implement `generate()` (request build, POST, poll, extract URI, return `VideoResponse`) and `edit()` (delegate to `generate()`). Use `$request->options ?? []` and guard against missing `uri` / poll timeout / API errors.
3. **VideoManager**: Register `createGoogleDriver()`.
4. **ServiceCatalog**: Add `google` and Veo model IDs under `video`.
5. **Docs / README**: Briefly document that the Google video driver uses the Gemini Veo API and that the returned `url` may require the same API key for download (if applicable).

---

## 9. Design Notes

- **Shared config**: Reuse `uniformed-ai.providers.google` (same key and base URL as chat). Only add `video_model` (or equivalent) for the default Veo model.
- **Auth**: Add `x-goog-api-key` in the driver per request; no change to `HttpClientFactory` required.
- **Base path**: Use `v1beta` for all Veo calls (e.g. `v1beta/models/...` and `v1beta/operations/...`). Ensure base URL does not already include `v1beta` (current default is `https://generativelanguage.googleapis.com`), so the path is `v1beta/models/...`.
- **No new DTOs**: `VideoRequest` and `VideoResponse` are sufficient; use `options` for Gemini-specific parameters and return `url` (+ optional `raw`) in `VideoResponse`.
- **Logging**: The existing `LoggingVideoDriver` and `LoggingDriverFactory` for `video` will wrap the Google driver when registered via `LoggingDriverFactory::wrap('video', 'google', ...)`.
- **Rate limits**: If the package has a rate-limit map per provider, add an entry for `google` video if needed (same provider key as chat).

---

## 10. Open Points

- **Download URI and API key**: Confirm whether the returned `video.uri` is a signed URL that already embeds auth or whether the client must send `x-goog-api-key` (or query param) when doing the GET. Document and, if needed, add a one-line note in the docblock for `VideoResponse::$url` for the Google driver.
- **Video extension**: Extension requires a Veo-generated video (or one from a previous call). The 2-day retention and “video from previous generation” constraint should be documented for callers who use `video_inline_data`.
- **Region / personGeneration**: Veo applies region-specific rules for `personGeneration`. The driver can pass through the option; callers are responsible for allowed values per region.

Once this plan is implemented, usage will look like:

- `AI::video()->driver('google')->generate(VideoRequest::make('A lion in the savannah.', 8, 'veo-3.1-generate-preview', ['resolution' => '1080p']));`
- Optional: set default video provider to `google` via `AI_VIDEO_PROVIDER=google` and then `AI::video()->generate(...)`.
