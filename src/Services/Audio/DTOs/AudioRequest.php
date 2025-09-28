<?php

namespace Iserter\UniformedAI\Services\Audio\DTOs;

class AudioRequest
{
    public function __construct(
        public string $text,
        public ?string $voice = null,
        public string $format = 'mp3',
        public ?string $model = null,
        public ?array $options = null,
    ) {}
}
