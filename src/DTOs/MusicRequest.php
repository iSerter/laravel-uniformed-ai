<?php

namespace Iserter\UniformedAI\DTOs;

class MusicRequest
{
    public function __construct(
        public string $text,
        public ?string $style = null,
        public string $format = 'mp3',
        public ?string $model = null,
        public ?array $options = null,
    ) {}
}
