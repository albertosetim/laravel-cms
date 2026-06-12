<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Entry extends Model
{
    use LogsActivity;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    protected $table = 'cms_entries';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'published_at' => 'datetime',
            'position' => 'integer',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ContentType::class, 'type_id');
    }

    public function scopeOfType(Builder $query, string $typeSlug): Builder
    {
        return $query->whereHas('type', fn (Builder $q) => $q->where('slug', $typeSlug));
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeInLocale(Builder $query, string $locale): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('locale', $locale)->orWhereNull('locale'));
    }

    /**
     * Filtro por campo do documento jsonb. Usa containment (@>) para que o
     * Postgres possa servir a query pelo índice GIN jsonb_path_ops.
     */
    public function scopeWhereField(Builder $query, string $field, mixed $value): Builder
    {
        return $query->whereRaw('data @> ?', [json_encode([$field => $value])]);
    }

    public function field(string $name, mixed $default = null): mixed
    {
        return data_get($this->data, $name, $default);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['type_id', 'slug', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
