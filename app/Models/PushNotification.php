<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushNotification extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'body',
        'url',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
