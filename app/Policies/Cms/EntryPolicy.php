<?php

namespace App\Policies\Cms;

use App\Models\Cms\Entry;
use App\Models\User;

class EntryPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['editor', 'publisher']);
    }

    public function view(User $user, Entry $entry): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['editor', 'publisher']);
    }

    public function update(User $user, Entry $entry): bool
    {
        return $user->hasAnyRole(['editor', 'publisher']);
    }

    public function delete(User $user, Entry $entry): bool
    {
        return $user->hasRole('publisher');
    }
}
