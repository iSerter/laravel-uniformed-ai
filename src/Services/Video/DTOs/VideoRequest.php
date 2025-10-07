<?php

namespace Iserter\UniformedAI\Services\Video\DTOs;

class VideoRequest
{
    public function __construct(
        public string $prompt,
        public ?int $durationSeconds = null,
        public ?string $model = null,
        public array $options = [],
    ) {}

    public static function make(string $prompt, ?int $durationSeconds = null, ?string $model = null, array $options = []): self
    {
        return new self($prompt, $durationSeconds, $model, $options);
    }
}
