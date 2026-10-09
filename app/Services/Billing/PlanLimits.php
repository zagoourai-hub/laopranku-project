<?php

namespace App\Services\Billing;

use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Carbon;
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
