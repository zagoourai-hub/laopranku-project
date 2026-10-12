<?php

namespace App\Services\Billing;

use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class PlanLimits
{
    /**
     * Documents created this calendar month, soft-deleted ones included so deleting
     * does not free quota (guests count the same). A force delete would still free it.
     */
    public function documentsThisMonth(User $user): int
    {
        return Document::withTrashed()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }

    public function canCreateDocument(User $user): bool
    {
        return $user->isPro() || $this->documentsThisMonth($user) < config('plans.free_documents_per_month');
    }

    /** AI generations allowed per day for this user's plan. */
    public function generationsPerDay(User $user): int
    {
        return (int) config($user->isPro() ? 'plans.pro_generations_per_day' : 'plans.free_generations_per_day');
    }

    public function generationsLeftToday(User $user): int
    {
        $used = DB::table('ai_generations')
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        return max(0, $this->generationsPerDay($user) - $used);
    }

    /**
     * Spend one AI generation for today. The user row is locked so two concurrent requests
     * cannot both take the last slot. Returns false when today's quota is used up.
     */
    public function spendGeneration(User $user, string $kind, int $units = 1): bool
    {
        return DB::transaction(function () use ($user, $kind, $units): bool {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($this->generationsLeftToday($locked) < $units) {
                return false;
            }

            $now = now();
            DB::table('ai_generations')->insert(array_fill(0, $units, [
                'user_id' => $locked->id,
                'kind' => $kind,
                'created_at' => $now,
            ]));

            return true;
        });
    }

    public function canExportPdf(User $user): bool
    {
        return $user->isPro() || ! config('plans.pdf_pro_only');
    }

    /** Upgrade page owned by the payments feature; '#' until that route exists. */
    public function upgradeUrl(): string
    {
        return Route::has('billing.upgrade') ? route('billing.upgrade') : '#';
    }

    /**
     * @return array{used: int, limit: int|null, isPro: bool, proUntil: Carbon|null}
     */
    public function quotaSummary(User $user): array
    {
        $isPro = $user->isPro();

        return [
            'used' => $this->documentsThisMonth($user),
            'limit' => $isPro ? null : (int) config('plans.free_documents_per_month'),
            'isPro' => $isPro,
            'proUntil' => $isPro ? $user->pro_until : null,
        ];
    }
}
