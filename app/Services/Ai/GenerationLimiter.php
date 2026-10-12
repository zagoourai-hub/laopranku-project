<?php

namespace App\Services\Ai;

use App\Services\Billing\PlanLimits;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Gate for every AI-backed action. Two checks, both must pass:
 * the per-minute burst limit (`ai-generate`) and the daily plan quota (one unit per chapter written).
 */
class GenerationLimiter
{
    public function __construct(private PlanLimits $plans) {}

    /** Check and spend in one call, so two concurrent requests cannot both pass the last slot. */
    public function consume(int $units = 1, string $kind = 'generate'): bool
    {
        $limit = RateLimiter::limiter('ai-generate')(request());

        if (RateLimiter::attempt($limit->key, $limit->maxAttempts, fn () => true, $limit->decaySeconds) !== true) {
            return false;
        }

        $user = auth()->user();

        return $user !== null && $this->plans->spendGeneration($user, $kind, $units);
    }

    /** True when the last refusal was the daily plan quota rather than the minute burst. */
    public function dailyQuotaExhausted(): bool
    {
        $user = auth()->user();

        return $user !== null && $this->plans->generationsLeftToday($user) <= 0;
    }
}
