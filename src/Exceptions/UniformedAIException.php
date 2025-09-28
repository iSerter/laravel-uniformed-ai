<?php

namespace Iserter\UniformedAI\Exceptions;

use Exception;

class UniformedAIException extends Exception
{
    public function __construct(
        string $message,
        public ?string $provider = null,
        public ?int $status = null,
        public ?array $raw = null
    ) {
        parent::__construct($message, $status ?? 0);
    }
}
