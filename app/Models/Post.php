<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory, SoftDeletes;

//    protected $guarded = [];
    protected $fillable = [
        'id',
        'user_id',
        'title',
        'ai_prompt_post',
        'ai_content',
        'ai_prompt_comment',
        'img',
        'img_ai_check_on',
        'channels',
        'preview',
        'published_at',
        'published',
        'task_complete',
        'check_attempts',
        'on_hold_until',
        'updated',
        'deleted',
        'deleted_at',
        'created_at',
        'updated_at',
    ];


    protected $dates = ['deleted_at'];

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
