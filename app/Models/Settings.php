<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Settings extends Model
{
    protected $fillable = [
        'user_id',
        'ai_personality',
        'ai_prompt_prefix',
        'openai_api_key',
        'meta_page_id',
    ];
}
