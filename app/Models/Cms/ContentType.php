<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Designer de um model: define campos e relações. Um gerador transforma este
 * spec em Model + Migration + FilamentResource reais (ver TypeGenerator).
 * Não guarda conteúdo — o conteúdo vive nos models gerados.
 */
class ContentType extends Model
{
    use LogsActivity;

    protected $table = 'cms_types';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'blueprint' => 'array',
            'relation_defs' => 'array',
            'options' => 'array',
            'generated' => 'boolean',
        ];
    }

    /** Campos do blueprint: [['name','label','type','required','listable'], ...] */
    public function fields(): array
    {
        return $this->blueprint['fields'] ?? [];
    }

    /** Relações: [['name','type'=>belongsTo|hasMany|belongsToMany,'target'=>FQCN], ...] */
    public function relationDefs(): array
    {
        return $this->relation_defs ?? [];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['slug', 'name', 'blueprint', 'relation_defs', 'generated'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
