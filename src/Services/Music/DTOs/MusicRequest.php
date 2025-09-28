<?php

namespace Iserter\UniformedAI\Services\Music\DTOs;

class MusicRequest
{
    public function __construct(
        public string $prompt,
        public ?string $model = null,
        public string $format = 'mp3',
        public ?array $options = null,
    ) {}
}
