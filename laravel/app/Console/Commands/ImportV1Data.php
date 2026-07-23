<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importa i dati reali dal DB v1 (connessione 'mariadb_legacy', sola lettura)
 * nello schema v2, preservando gli ID originali per non rompere le foreign key.
 *
 * Ordine di import rispetta le dipendenze: users -> (settings, posts, token_logs,
 * image_jobs, push_subscriptions) -> comments (dipende da posts) -> push_notifications
 * (dipende da users, per calcolare created_by_user_id che v1 non ha).
 *
 * Idempotente: usa insertOrIgnore, rilanciarlo non duplica righe già importate
 * ne' sovrascrive modifiche fatte in v2 dopo un primo import.
 */
#[Signature('v1:import {--dry-run : Conta le righe da importare per tabella senza scrivere nulla}')]
#[Description('Importa i dati reali dal DB v1 legacy (connessione mariadb_legacy) nello schema v2')]
class ImportV1Data extends Command
{
    private const LEGACY = 'mariadb_legacy';

    public function handle(): void
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->importUsers($dryRun);
        $this->importSettings($dryRun);
        $this->importPosts($dryRun);
        $this->importTokenLogs($dryRun);
        $this->importImageJobs($dryRun);
        $this->importPushSubscriptions($dryRun);
        $this->importComments($dryRun);
        $this->importPushNotifications($dryRun);

        $this->newLine();
        $this->info($dryRun ? 'Dry-run completato, nessuna scrittura eseguita.' : 'Import completato.');
    }

    private function importUsers(bool $dryRun): void
    {
        $this->copyTable('users', $dryRun, fn (object $u) => [
            'id' => $u->id,
            'parent_id' => $u->parent_id,
            'name' => $u->name,
            'child_on' => $u->child_on,
            'child_max' => $u->child_max,
            'notify_read_browser' => $u->notify_read_browser,
            'notify_read_web' => $u->notify_read_web,
            'channels' => $u->channels,
            'tokens_limit' => $u->tokens_limit,
            'image_model_limit' => $u->image_model_limit,
            'email' => $u->email,
            'email_verified_at' => $u->email_verified_at,
            'password' => $u->password,
            'remember_token' => $u->remember_token,
            'created_at' => $u->created_at,
            'updated_at' => $u->updated_at,
        ]);
    }

    /**
     * Rinomina mailchimp_/brevo_ -> nl_mailchimp_/nl_brevo_ e unifica i 4
     * campi template per-provider in nl_template/nl_template_cta, stessa
     * logica della migration 2026_07_23_100000_rename_newsletter_columns_and_unify_template.
     * Le colonne nl_smtp_* e linkedin_token_expires_at restano null (v1 non le ha).
     */
    private function importSettings(bool $dryRun): void
    {
        $this->copyTable('settings', $dryRun, fn (object $s) => [
            'id' => $s->id,
            'user_id' => $s->user_id,

            'ai_personality' => $s->ai_personality,
            'ai_prompt_prefix' => $s->ai_prompt_prefix,
            'ai_comment_prefix' => $s->ai_comment_prefix,

            'openai_api_key' => $s->openai_api_key,
            'meta_page_id' => $s->meta_page_id,

            'linkedin_person_id' => $s->linkedin_person_id,
            'linkedin_company_id' => $s->linkedin_company_id,
            'linkedin_client_id' => $s->linkedin_client_id,
            'linkedin_client_secret' => $s->linkedin_client_secret,
            'linkedin_token' => $s->linkedin_token,

            'wordpress_url' => $s->wordpress_url,
            'wordpress_username' => $s->wordpress_username,
            'wordpress_password' => $s->wordpress_password,
            'wordpress_cat_id' => $s->wordpress_cat_id,
            'wordpress_options' => $s->wordpress_options,

            'nl_mailchimp_api' => $s->mailchimp_api,
            'nl_mailchimp_datacenter' => $s->mailchimp_datacenter,
            'nl_mailchimp_list_id' => $s->mailchimp_list_id,
            'nl_mailchimp_from_name' => $s->mailchimp_from_name,
            'nl_mailchimp_from_email' => $s->mailchimp_from_email,
            'nl_mailchimp_options' => $s->mailchimp_options,

            'nl_brevo_api' => $s->brevo_api,
            'nl_brevo_list_id' => $s->brevo_list_id,
            'nl_brevo_from_name' => $s->brevo_from_name,
            'nl_brevo_from_email' => $s->brevo_from_email,
            'nl_brevo_options' => $s->brevo_options,

            'nl_template' => $s->mailchimp_template ?? $s->brevo_template,
            'nl_template_cta' => $s->mailchimp_template_cta ?? $s->brevo_template_cta,

            'created_at' => $s->created_at,
            'updated_at' => $s->updated_at,
        ]);
    }

    private function importPosts(bool $dryRun): void
    {
        // comments_enabled/auto_reply_enabled non esistono in v1: si omettono,
        // prendono il default di schema (1/0), come per un post mai personalizzato.
        $this->copyTable('posts', $dryRun, fn (object $p) => [
            'id' => $p->id,
            'user_id' => $p->user_id,
            'created_by_user_id' => $p->created_by_user_id,
            'title' => $p->title,
            'ai_prompt_post' => $p->ai_prompt_post,
            'ai_content' => $p->ai_content,
            'ai_prompt_comment' => $p->ai_prompt_comment,
            'img' => $p->img,
            'img_ai_check_on' => $p->img_ai_check_on,
            'channels' => $p->channels,
            'preview' => $p->preview,
            'published_at' => $p->published_at,
            'published' => $p->published,
            'task_complete' => $p->task_complete,
            'check_attempts' => $p->check_attempts,
            'on_hold_until' => $p->on_hold_until,
            'updated' => $p->updated,
            'deleted' => $p->deleted,
            'deleted_at' => $p->deleted_at,
            'created_at' => $p->created_at,
            'updated_at' => $p->updated_at,
        ]);
    }

    private function importComments(bool $dryRun): void
    {
        $this->copyTable('comments', $dryRun, fn (object $c) => [
            'id' => $c->id,
            'post_id' => $c->post_id,
            'channel' => $c->channel,
            'from_id' => $c->from_id,
            'from_name' => $c->from_name,
            'message_id' => $c->message_id,
            'message' => $c->message,
            'message_created_time' => $c->message_created_time,
            'reply_id' => $c->reply_id,
            'reply' => $c->reply,
            'reply_created_time' => $c->reply_created_time,
            'created_at' => $c->created_at,
            'updated_at' => $c->updated_at,
        ]);
    }

    private function importTokenLogs(bool $dryRun): void
    {
        $this->copyTable('token_logs', $dryRun, fn (object $t) => [
            'id' => $t->id,
            'user_id' => $t->user_id,
            'type' => $t->type,
            'reference_id' => $t->reference_id,
            'tokens_used' => $t->tokens_used,
            'created_at' => $t->created_at,
            'updated_at' => $t->updated_at,
        ]);
    }

    /**
     * v1 non distingue "proprietario" da "chi ha lanciato la generazione":
     * created_by_user_id (colonna aggiunta solo in v2) viene valorizzato
     * uguale a user_id.
     */
    private function importImageJobs(bool $dryRun): void
    {
        $this->copyTable('image_jobs', $dryRun, fn (object $i) => [
            'id' => $i->id,
            'user_id' => $i->user_id,
            'created_by_user_id' => $i->user_id,
            'status' => $i->status,
            'image_url' => $i->image_url,
            'prompt' => $i->prompt,
            'model' => $i->model,
            'created_at' => $i->created_at,
            'updated_at' => $i->updated_at,
        ]);
    }

    private function importPushSubscriptions(bool $dryRun): void
    {
        $this->copyTable('push_subscriptions', $dryRun, fn (object $p) => [
            'id' => $p->id,
            'subscribable_type' => $p->subscribable_type,
            'subscribable_id' => $p->subscribable_id,
            'endpoint' => $p->endpoint,
            'public_key' => $p->public_key,
            'auth_token' => $p->auth_token,
            'content_encoding' => $p->content_encoding,
            'created_at' => $p->created_at,
            'updated_at' => $p->updated_at,
        ]);
    }

    /**
     * v1 non ha ne' 'audience' ne' un concetto di creatore: ogni riga era
     * sempre per un destinatario specifico (user_id). Import best-effort:
     * audience resta null quando c'e' un destinatario preciso (recipients_count
     * = 1), created_by_user_id (obbligatorio in v2) e' desunto come il
     * parent_id del destinatario (chi gestisce l'account), o il destinatario
     * stesso se non ha parent (root/admin) - non e' ricostruibile con
     * certezza dai dati v1, e' la scelta piu' sensata disponibile.
     * Le rare righe v1 con user_id nullo (mai un caso reale visto finora,
     * ma la colonna lo permette) diventano audience='all' attribuite al
     * primo admin (parent_id nullo), recipients_count = utenti totali.
     */
    private function importPushNotifications(bool $dryRun): void
    {
        $usersParent = DB::table('users')->pluck('parent_id', 'id');
        $rootAdminId = DB::table('users')->whereNull('parent_id')->min('id');
        $totalUsers = DB::table('users')->count();

        $this->copyTable('push_notifications', $dryRun, function (object $n) use ($usersParent, $rootAdminId, $totalUsers) {
            $recipientId = $n->user_id;

            if ($recipientId !== null) {
                return [
                    'id' => $n->id,
                    'created_by_user_id' => $usersParent[$recipientId] ?? $recipientId,
                    'user_id' => $recipientId,
                    'audience' => null,
                    'title' => $n->title,
                    'body' => $n->body,
                    'url' => $n->url,
                    'sent_at' => $n->sent_at,
                    'recipients_count' => 1,
                    'created_at' => $n->created_at,
                    'updated_at' => $n->updated_at,
                ];
            }

            return [
                'id' => $n->id,
                'created_by_user_id' => $rootAdminId,
                'user_id' => null,
                'audience' => 'all',
                'title' => $n->title,
                'body' => $n->body,
                'url' => $n->url,
                'sent_at' => $n->sent_at,
                'recipients_count' => $totalUsers,
                'created_at' => $n->created_at,
                'updated_at' => $n->updated_at,
            ];
        });
    }

    /**
     * @param  callable(object): array<string, mixed>  $map
     */
    private function copyTable(string $table, bool $dryRun, callable $map): void
    {
        $total = DB::connection(self::LEGACY)->table($table)->count();

        if ($total === 0) {
            $this->line("{$table}: 0 righe nel DB legacy, salto.");

            return;
        }

        if ($dryRun) {
            $this->line("{$table}: {$total} righe da importare.");

            return;
        }

        $imported = 0;
        DB::connection(self::LEGACY)->table($table)->orderBy('id')
            ->chunkById(500, function ($rows) use ($table, $map, &$imported) {
                $rows = $rows->map($map)->all();
                DB::table($table)->insertOrIgnore($rows);
                $imported += count($rows);
            });

        $this->info("{$table}: {$imported}/{$total} righe importate (o gia' presenti).");
    }
}
