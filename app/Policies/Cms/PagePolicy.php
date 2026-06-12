<?php

namespace App\Policies\Cms;

use App\Models\Cms\Page;
use App\Models\User;

class PagePolicy
{
    /** Admin passa em tudo. */
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['editor', 'publisher']);
    }

    public function view(User $user, Page $page): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['editor', 'publisher']);
    }

    public function update(User $user, Page $page): bool
    {
        return $user->hasAnyRole(['editor', 'publisher']);
    }

    public function delete(User $user, Page $page): bool
    {
        return $user->hasRole('publisher');
    }

    public function restore(User $user, Page $page): bool
    {
        return $user->hasRole('publisher');
    }

    public function forceDelete(User $user, Page $page): bool
    {
        return false;
    }

    /** Publicar é permissão separada de editar (workflow draft → publish). */
    public function publish(User $user, Page $page): bool
    {
        return $user->hasRole('publisher');
    }
}
