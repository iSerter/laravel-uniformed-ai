<?php

namespace Iserter\UniformedAI\Services\Chat\DTOs;

class ChatMessage
{
    public function __construct(
        public string $role, // system|user|assistant|tool
        public string $content = '',
        public ?array $attachments = null, // images/audio refs etc
        public ?string $name = null,
        public ?array $toolCalls = null,
    ) {}
}
