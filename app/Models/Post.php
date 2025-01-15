<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'post_id')
            ->orderBy('message_created_time', 'desc');
    }

    public function token()
    {
        return $this->hasOne(Token_log::class, 'reference_id')
            ->where('type', 'post');
    }
}
