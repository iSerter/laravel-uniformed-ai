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
    ) {
        $this->validate();
    }

    protected function validate(): void
    {
        $allowed = ['system','user','assistant','tool'];
        if (!in_array($this->role, $allowed, true)) {
            throw new \Iserter\UniformedAI\Exceptions\ValidationException("Invalid chat message role '{$this->role}'. Allowed: ".implode(',', $allowed));
        }
        if ($this->role !== 'tool' && trim($this->content) === '') {
            throw new \Iserter\UniformedAI\Exceptions\ValidationException('Chat message content cannot be empty.');
        }
        if ($this->role === 'tool' && empty($this->toolCalls)) {
            // Tool role usually carries tool call result or metadata; allow empty content but still ensure some data present
            if (trim($this->content) === '') {
                throw new \Iserter\UniformedAI\Exceptions\ValidationException('Tool message must have content or toolCalls.');
            }
        }
    }
}
