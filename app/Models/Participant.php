<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Participant extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'email', 'project_id'];

    protected static function booted()
    {
        static::creating(function ($model) {

            $model->uuid = Str::uuid();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | RELACIONAMENTO CERTIFICADOS
    |--------------------------------------------------------------------------
    */

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(
            Certificate::class
        );
    }

    public function latestCertificate()
    {
        return $this->hasOne(
            Certificate::class
        )->latestOfMany();
    }

}
