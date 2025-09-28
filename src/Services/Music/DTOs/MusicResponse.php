<?php

namespace Iserter\UniformedAI\Services\Music\DTOs;

class MusicResponse
{
    public function __construct(
        public string $b64Audio,
        public ?array $raw = null,
    ) {}
}
