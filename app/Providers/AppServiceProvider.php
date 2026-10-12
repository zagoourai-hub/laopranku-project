<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiters();
    }

    protected function configureRateLimiters(): void
    {
        RateLimiter::for('downloads', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));

        // Per-minute burst for AI actions, spent through GenerationLimiter. Guests are keyed by IP.
        RateLimiter::for('ai-generate', fn (Request $request) => $request->user() && ! $request->user()->is_guest
            ? Limit::perMinute(5)->by('u'.$request->user()->id)
            : Limit::perMinute((int) config('plans.guest_generations_per_ip_per_minute'))->by('ip'.$request->ip()));

        // Guest accounts are free to create, so cap them per IP per day as well as per minute.
        RateLimiter::for('guest-start', fn (Request $request) => [
            Limit::perMinute(10)->by('min'.$request->ip()),
            Limit::perDay((int) config('plans.guest_accounts_per_ip_per_day'))->by('day'.$request->ip()),
        ]);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
