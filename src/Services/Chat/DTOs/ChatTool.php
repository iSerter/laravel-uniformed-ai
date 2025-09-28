<?php

namespace Iserter\UniformedAI\Services\Chat\DTOs;

class ChatTool
{
    public function __construct(
        public string $name,
        public string $description,
        public array $parameters // JSON Schema
    ) {}
}
