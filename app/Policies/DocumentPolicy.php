<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
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
