<?php

namespace App\Policies;

use App\Models\ManagedDocument;
use App\Models\User;

class ManagedDocumentPolicy
{
    public function view(User $user, ManagedDocument $document): bool
    {
        return ManagedDocument::query()->visibleTo($user)->whereKey($document->id)->exists();
    }
}
