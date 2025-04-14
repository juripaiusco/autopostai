<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Settings extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ai_personality',
        'ai_prompt_prefix',
        'openai_api_key',
        'meta_page_id',
        'wordpress_url',
        'wordpress_username',
        'wordpress_password',
        'wordpress_cat_id',
        'mailchimp_api',
        'mailchimp_datacenter',
        'mailchimp_list_id',
        'mailchimp_from_name',
        'mailchimp_from_email',
        'mailchimp_template',
        'mailchimp_template_cta',
        'brevo_api',
        'brevo_list_id',
        'brevo_from_name',
        'brevo_from_email',
        'brevo_template',
        'brevo_template_cta',
    ];
}
