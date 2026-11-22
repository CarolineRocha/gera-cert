<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailLog extends Model
{
    use SoftDeletes;

    protected $fillable = [

        'participant_id',

        'sent_by_user_id',

        'certificate_id',

        'template_id',

        'subject',

        'recipient',

        'rendered_html',

        'status',

        'error_message',

        'sent_at'
    ];

    protected static function booted()
    {
        static::creating(function ($model) {

            $model->uuid = Str::uuid();
        });
    }

    public function sentBy()
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }
}
