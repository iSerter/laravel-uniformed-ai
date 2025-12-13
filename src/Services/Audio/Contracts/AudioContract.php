<?php

namespace Iserter\UniformedAI\Services\Audio\Contracts;

use Iserter\UniformedAI\Services\Audio\DTOs\AudioRequest;
use Iserter\UniformedAI\Services\Audio\DTOs\AudioResponse;
use Iserter\UniformedAI\Services\Audio\DTOs\AudioTranscriptionRequest;
use Iserter\UniformedAI\Services\Audio\DTOs\AudioTranscriptionResponse;

interface AudioContract
{
    public function speak(AudioRequest $request): AudioResponse; // text->speech

    public function transcribe(AudioTranscriptionRequest $request): AudioTranscriptionResponse; // speech->text

    /**
     * Retrieve list of available voices for the underlying provider.
     * Implementations may cache results; pass $refresh=true to force re-fetch.
     * Returned values should be simple associative arrays (id => name) or a flat list of IDs.
     * @return array<int|string, mixed>
     */
    public function getAvailableVoices(bool $refresh = false): array;
}
