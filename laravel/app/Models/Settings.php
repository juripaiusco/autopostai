<?php

namespace App\Models;

use Database\Factories\SettingsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

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
    'nl_mailchimp_api',
    'nl_mailchimp_datacenter',
    'nl_mailchimp_list_id',
    'nl_mailchimp_from_name',
    'nl_mailchimp_from_email',
    'nl_mailchimp_options',
    'nl_brevo_api',
    'nl_brevo_list_id',
    'nl_brevo_from_name',
    'nl_brevo_from_email',
    'nl_brevo_options',
    'nl_template',
    'nl_template_cta',
    'nl_smtp_host',
    'nl_smtp_port',
    'nl_smtp_username',
    'nl_smtp_password',
    'nl_smtp_encryption',
    'nl_smtp_sender',
    'contacts_api_key_hash',
    'contacts_api_key_last_four',
    'contacts_api_key_created_at',
])]
class Settings extends Model
{
    /** @use HasFactory<SettingsFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'linkedin_token_expires_at' => 'datetime',
            'wordpress_options' => 'array',
            'nl_mailchimp_options' => 'array',
            'nl_brevo_options' => 'array',
            'contacts_api_key_created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Provider newsletter attivo: "chi ha una API key impostata vince" (stessa
     * regola di v1) — precedenza mailchimp > brevo > smtp custom (nessuna
     * lista esterna, destinatari = contacts interni). Unica fonte di verità,
     * usata sia da NewsletterController (fetch liste) sia dalla visibilità
     * del modulo Contatti in sidebar.
     */
    public function newsletterProvider(): ?string
    {
        if (!empty($this->nl_mailchimp_api)) {
            return 'mailchimp';
        }
        if (!empty($this->nl_brevo_api)) {
            return 'brevo';
        }
        if (!empty($this->nl_smtp_host)) {
            return 'smtp_custom';
        }

        return null;
    }

    /**
     * Genera una nuova API key per la registrazione contatti server-to-server
     * (Step 5): si salva solo l'hash (stesso principio di Sanctum), il
     * plaintext ritornato qui è l'unica volta in cui è leggibile — va
     * mostrato una tantum a chi configura il sistema esterno.
     */
    public function generateContactsApiKey(): string
    {
        $plaintext = Str::random(40);

        $this->update([
            'contacts_api_key_hash' => hash('sha256', $plaintext),
            'contacts_api_key_last_four' => substr($plaintext, -4),
            'contacts_api_key_created_at' => now(),
        ]);

        return $plaintext;
    }
}
