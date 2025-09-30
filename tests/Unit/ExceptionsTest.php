<?php

use Iserter\UniformedAI\Exceptions\{ProviderException, RateLimitException, ValidationException, AuthenticationException};

it('instantiates custom exceptions with context', function() {
    $e = new ProviderException('fail', provider: 'openai', status: 500, raw: ['error'=>'x']);
    expect($e->provider)->toBe('openai');
    expect($e->status)->toBe(500);
    $rle = new RateLimitException('rl', provider: 'openai', status: 429);
    expect($rle->getCode())->toBe(429);
    $ve = new ValidationException('bad', provider: 'openai');
    $ae = new AuthenticationException('auth', provider: 'openai');
    expect([$ve, $ae])->toHaveCount(2);
});
