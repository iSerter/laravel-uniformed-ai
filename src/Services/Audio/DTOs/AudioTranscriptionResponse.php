<?php

namespace Iserter\UniformedAI\Services\Audio\DTOs;

class AudioTranscriptionResponse
{
    public function __construct(
        public string $text, // Transcribed text
        public ?string $language = null, // Detected or specified language
        public ?float $duration = null, // Audio duration in seconds
        public ?array $raw = null, // Raw provider response for debugging/metadata
    ) {}
}
