<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Services\Billing\PlanLimits;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Free plan monthly quota applies to every new document (create page, duplicate).
     */
    public function create(User $user): bool
    {
        return app(PlanLimits::class)->canCreateDocument($user);
    }

    public function view(User $user, Document $document): bool
    {
        return $this->owns($user, $document);
    }

    public function update(User $user, Document $document): bool
    {
        return $this->owns($user, $document);
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->owns($user, $document);
    }

    public function restore(User $user, Document $document): bool
    {
        return $this->owns($user, $document);
    }

    public function forceDelete(User $user, Document $document): bool
    {
        return $this->owns($user, $document);
    }

    private function owns(User $user, Document $document): bool
    {
        return $user->id === $document->user_id;
    }
}
