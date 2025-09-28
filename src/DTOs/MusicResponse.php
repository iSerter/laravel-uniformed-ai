<?php

namespace Iserter\UniformedAI\DTOs;

class MusicResponse
{
    public function __construct(
        public string $b64Audio,
        public ?array $raw = null,
    ) {}
}
