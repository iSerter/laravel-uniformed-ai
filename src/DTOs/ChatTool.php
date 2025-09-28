<?php

namespace Iserter\UniformedAI\DTOs;

class ChatTool
{
    public function __construct(
        public string $name,
        public string $description,
        public array $parameters // JSON Schema
    ) {}
}
