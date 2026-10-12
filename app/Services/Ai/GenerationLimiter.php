<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Spends one unit of the `ai-generate` budget for the current user. Every AI-backed action goes through here.
 */
class GenerationLimiter
{
    /** Check and spend in one call, so two concurrent requests cannot both pass the last slot. */
    public function consume(): bool
    {
        $limit = RateLimiter::limiter('ai-generate')(request());

        return RateLimiter::attempt($limit->key, $limit->maxAttempts, fn () => true, $limit->decaySeconds) === true;
    }
}
