<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Catálogo de plugins (autoridade do estado enabled). O boot NUNCA lê esta
 * tabela (G3) — lê o cache materializado por cms:plugins:sync.
 */
class Plugin extends Model
{
    use LogsActivity;

    protected $table = 'cms_plugins';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['slug', 'enabled'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
