<?php

namespace Iserter\UniformedAI\Services\Chat\DTOs;

class ChatRequest
{
    /** @param ChatMessage[] $messages */
    public function __construct(
        public array $messages,
        public ?string $model = null,
        public ?float $temperature = null,
        public ?int $maxTokens = null,
        /** @var ChatTool[]|null */
        public ?array $tools = null,
        public ?string $toolChoice = null, // auto|none|required|name
        public ?array $metadata = null,
    ) {}
}
