<?php

namespace Iserter\UniformedAI\Exceptions;

class ProviderException extends UniformedAIException {
	/** Convenience accessor for upstream HTTP status */
	public function httpStatus(): ?int { return $this->status ?: null; }
}
