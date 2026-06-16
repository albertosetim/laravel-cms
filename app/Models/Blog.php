<?php

namespace App\Models;

use App\Models\Concerns\HasSettings;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Blog extends Model
{
    use HasSettings;
    use LogsActivity;

    protected $fillable = ['title', 'slug', 'status'];

    protected function casts(): array
    {
        return [

        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'slug', 'status'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
