<?php

namespace Iserter\UniformedAI\Services\Video\DTOs;

class VideoResponse
{
    public function __construct(
        public string $b64Video, // base64 of binary video (mp4/gif/webm depending on provider option)
        public ?string $format = null,
        public array $raw = [], // provider raw response (optional)
    ) {}
}
