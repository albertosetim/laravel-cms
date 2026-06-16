<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Item da biblioteca de media (gestor geral de ficheiros). Cada registo agrupa
 * um ou mais ficheiros sob a collection "media", geridos pelo spatie/laravel-medialibrary.
 */
class MediaItem extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'cms_media_items';

    protected $guarded = ['id'];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('media');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(200)
            ->height(200)
            ->nonQueued();
    }
}
