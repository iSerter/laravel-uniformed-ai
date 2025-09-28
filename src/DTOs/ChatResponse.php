<?php

namespace Iserter\UniformedAI\DTOs;

class ChatResponse
{
    public function __construct(
        public string $content,
        public ?array $toolCalls = null,
        public ?string $model = null,
        public ?array $raw = null,
    ) {}
}
