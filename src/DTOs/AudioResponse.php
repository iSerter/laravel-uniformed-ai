<?php

namespace Iserter\UniformedAI\DTOs;

class AudioResponse
{
    public function __construct(
        public string $b64Audio,
        public ?array $raw = null,
    ) {}
}
