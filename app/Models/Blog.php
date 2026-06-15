<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Blog extends Model
{
    protected $fillable = ['title', 'slug', 'status'];

    protected function casts(): array
    {
        return [

        ];
    }


}
