<?php

namespace App\Policies\Cms;

use App\Models\Cms\ContentType;
use App\Models\User;

/**
 * Criar/alterar TIPOS é a permissão mais poderosa do sistema (muda
 * schema-de-dados) — só admin (07-backend).
 */
class ContentTypePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, ContentType $type): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ContentType $type): bool
    {
        return false;
    }

    public function delete(User $user, ContentType $type): bool
    {
        return false;
    }
}
