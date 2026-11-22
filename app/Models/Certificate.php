<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Certificate extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
        ];
    }

    protected $fillable = [
        'participant_id',
        'template_id',
        'pdf_path',
        'rendered_html',
        'status',
        'generated_at'
    ];

    protected static function booted()
    {
        static::creating(function ($model) {

            $model->uuid = Str::uuid();
        });
    }
    
    public function participant()
    {
        return $this->belongsTo(
            Participant::class
        );
    }
}
