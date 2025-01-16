<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory;

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function token()
    {
        return $this->hasOne(Token_log::class, 'reference_id')
            ->where('type', 'reply');
    }
}
