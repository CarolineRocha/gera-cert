<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Project extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id', 'name'];

    protected static function booted()
    {
        static::creating(function ($model) {

            $model->uuid = Str::uuid();
            $model->user_id ??= Auth::id();
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }
}
