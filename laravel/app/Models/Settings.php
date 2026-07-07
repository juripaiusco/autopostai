<?php

namespace App\Models;

use Database\Factories\SettingsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'ai_personality',
    'ai_prompt_prefix',
    'ai_comment_prefix',
    'openai_api_key',
    'meta_page_id',
    'linkedin_person_id',
    'linkedin_company_id',
    'linkedin_client_id',
    'linkedin_client_secret',
    'linkedin_token',
    'wordpress_url',
    'wordpress_username',
    'wordpress_password',
    'wordpress_cat_id',
    'wordpress_options',
    'mailchimp_api',
    'mailchimp_datacenter',
    'mailchimp_list_id',
    'mailchimp_from_name',
    'mailchimp_from_email',
    'mailchimp_template',
    'mailchimp_template_cta',
    'mailchimp_options',
    'brevo_api',
    'brevo_list_id',
    'brevo_from_name',
    'brevo_from_email',
    'brevo_template',
    'brevo_template_cta',
    'brevo_options',
])]
class Settings extends Model
{
    /** @use HasFactory<SettingsFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'linkedin_token_expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
