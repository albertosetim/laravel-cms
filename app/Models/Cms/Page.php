<?php

namespace App\Models\Cms;

use App\Services\Cms\PageTree;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Page extends Model
{
    use LogsActivity;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    protected $table = 'cms_pages';

    protected $guarded = ['id', 'published_revision_id'];

    protected function casts(): array
    {
        return [
            'show_in_menu' => 'boolean',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Page $page) {
            $page->translation_group_id ??= (string) Str::uuid();
        });

        // Qualquer mutação estrutural invalida árvore + lookup de paths.
        static::saved(fn () => app(PageTree::class)->flush());
        static::deleted(fn () => app(PageTree::class)->flush());
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class, 'page_id')->latest('created_at');
    }

    public function draftRevision(): HasOne
    {
        return $this->hasOne(PageRevision::class, 'page_id')->where('is_draft', true);
    }

    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(PageRevision::class, 'published_revision_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(self::class, 'translation_group_id', 'translation_group_id')
            ->whereKeyNot($this->getKey());
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED && $this->published_revision_id !== null;
    }

    /** Cadeia de slugs desde a raiz, sem o slug da homepage. */
    public function path(): string
    {
        $segments = [];
        $node = $this;

        while ($node !== null) {
            $segments[] = $node->slug;
            $node = $node->parent;
        }

        $segments = array_reverse($segments);

        if ($segments === [config('cms.home_slug')]) {
            return '';
        }

        return implode('/', $segments);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['slug', 'name', 'template', 'parent_id', 'position', 'status', 'locale'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
