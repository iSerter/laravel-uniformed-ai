<?php

namespace Iserter\UniformedAI\Services\Video\DTOs;

class VideoResponse
{
    public function __construct(
        /** Base64-encoded video; avoid for large files (e.g. 100MB+) to prevent OOM. Prefer {@see url} when driver provides it. */
        public ?string $b64Video = null,
        /** Download URL for the video (e.g. Veo/Kling); use when available instead of loading base64 into memory. */
        public ?string $url = null,
        public ?string $format = null,
        public array $raw = [], // provider raw response (optional)
    ) {}

    /** Whether the response has binary content (base64). Prefer {@see hasUrl()} for large videos. */
    public function hasB64Video(): bool
    {
        return $this->b64Video !== null && $this->b64Video !== '';
    }

    /** Whether the response has a download URL. Prefer this over base64 for large files. */
    public function hasUrl(): bool
    {
        return $this->url !== null && $this->url !== '';
    }
}
