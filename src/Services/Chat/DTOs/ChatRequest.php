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
    ) {
        $this->validate();
    }

    protected function validate(): void
    {
        if (empty($this->messages)) {
            throw new \Iserter\UniformedAI\Exceptions\ValidationException('ChatRequest must include at least one message.');
        }
        foreach ($this->messages as $m) {
            if (!$m instanceof ChatMessage) {
                throw new \Iserter\UniformedAI\Exceptions\ValidationException('All messages must be instances of ChatMessage.');
            }
        }
        if ($this->temperature !== null && ($this->temperature < 0 || $this->temperature > 2)) {
            throw new \Iserter\UniformedAI\Exceptions\ValidationException('Temperature must be between 0 and 2.');
        }
        if ($this->maxTokens !== null && $this->maxTokens <= 0) {
            throw new \Iserter\UniformedAI\Exceptions\ValidationException('maxTokens must be positive when provided.');
        }
        if ($this->toolChoice !== null) {
            $allowedChoices = ['auto','none','required'];
            // Named tool choice allowed if starts with alpha
            if (!in_array($this->toolChoice, $allowedChoices, true) && !preg_match('/^[A-Za-z][A-Za-z0-9_\-]*$/', $this->toolChoice)) {
                throw new \Iserter\UniformedAI\Exceptions\ValidationException('Invalid toolChoice value.');
            }
        }
    }
}
