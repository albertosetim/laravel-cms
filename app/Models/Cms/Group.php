<?php

namespace App\Models\Cms;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Grupo de utilizadores (organização/equipa). Ortogonal aos roles do Spatie:
 * roles dão permissões, grupos agrupam pessoas.
 */
class Group extends Model
{
    protected $table = 'cms_groups';

    protected $guarded = ['id'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'cms_group_user');
    }
}
