<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletes;

class Template extends Model
{
    use SoftDeletes;

    protected static function booted()
    {
        static::creating(function ($model) {

            $model->uuid = Str::uuid();
        });
    }
}
