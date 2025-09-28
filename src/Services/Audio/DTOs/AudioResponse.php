<?php

namespace Iserter\UniformedAI\Services\Audio\DTOs;

class AudioResponse
{
    public function __construct(
        public string $b64Audio,
        public ?array $raw = null,
    ) {}
}
