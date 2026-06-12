<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ContentType extends Model
{
    use LogsActivity;

    protected $table = 'cms_types';

    protected $guarded = ['id', 'promoted'];

    protected function casts(): array
    {
        return [
            'blueprint' => 'array',
            'options' => 'array',
            'promoted' => 'boolean',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class, 'type_id');
    }

    /** Campos do blueprint: [['name','label','type','required','options','listable'], ...] */
    public function fields(): array
    {
        return $this->blueprint['fields'] ?? [];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['slug', 'name', 'blueprint'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
