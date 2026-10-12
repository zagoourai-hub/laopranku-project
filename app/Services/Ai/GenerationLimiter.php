<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Services\Billing\PlanLimits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Gate for every AI-backed action. Two checks, both must pass:
 * the per-minute burst limit (`ai-generate`) and the daily plan quota (one unit per chapter written).
 */
class GenerationLimiter
{
    public function __construct(private PlanLimits $plans) {}

    /** For the signed-in web user. Services and MCP call consumeFor() with an explicit user. */
    public function consume(int $units = 1, string $kind = 'generate'): bool
    {
        $user = auth()->user();

        return $user !== null && $this->consumeFor($user, $units, $kind, request()->ip());
    }

    /** Check and spend in one call, so two concurrent requests cannot both pass the last slot. */
    public function consumeFor(User $user, int $units = 1, string $kind = 'generate', ?string $ip = null): bool
    {
        // Evaluate the one `ai-generate` definition (AppServiceProvider) against this user and IP.
        $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => $ip ?? '127.0.0.1']);
        $request->setUserResolver(fn () => $user);
        $limit = RateLimiter::limiter('ai-generate')($request);

        if (RateLimiter::attempt($limit->key, $limit->maxAttempts, fn () => true, $limit->decaySeconds) !== true) {
            return false;
        }

        return $this->plans->spendGeneration($user, $kind, $units, $ip);
    }

    /** True when the last refusal was the daily quota (plan or guest IP) rather than the minute burst. */
    public function dailyQuotaExhausted(int $units = 1): bool
    {
        $user = auth()->user();

        return $user !== null && $this->dailyQuotaExhaustedFor($user, $units, request()->ip());
    }

    public function dailyQuotaExhaustedFor(User $user, int $units = 1, ?string $ip = null): bool
    {
        return $this->plans->generationsLeftToday($user, $ip) < $units;
    }

    /** Indonesian message for a refusal, telling the daily quota apart from the minute burst. */
    public function refusalMessage(User $user, int $units = 1, ?string $ip = null): string
    {
        return $this->dailyQuotaExhaustedFor($user, $units, $ip)
            ? __('Kuota generate AI hari ini sudah habis. Coba lagi besok atau upgrade ke Pro.')
            : __('Terlalu banyak permintaan AI. Coba lagi beberapa menit lagi.');
    }
}
