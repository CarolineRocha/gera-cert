<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmtpSetting extends Model
{
    protected $fillable = [
        'user_id',
        'scheme',
        'host',
        'port',
        'username',
        'password',
        'from_address',
        'from_name',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
