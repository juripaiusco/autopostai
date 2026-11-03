<?php

namespace App\Actions\Posts;

use App\Models\ContactTag;
use App\Models\Post;
use App\Models\User;

/**
 * Costruzione del JSON channels di un post dai dati del form (o di un tool
 * MCP): stesse regole per chiunque salvi un post.
 */
class PostChannels
{
    /**
     * Ricostruisce i canali selezionati per il post a partire dal payload
     * grezzo: whitelist dei campi attesi per tipo di canale, non un
     * pass-through — coerente con come AccountController tratta i canali.
     * I canali non presenti nel payload restano semplicemente 'on' => false.
     *
     * $account è l'account target: il tetto di risposte automatiche
     * (reply_n) e' una preferenza account-level (impostata in Account) ma
     * viene CONGELATO nel post al salvataggio, cosi' il worker di
     * pubblicazione applica le regole leggendo solo il post, senza dover
     * risalire all'account (comportamento voluto, coerente con v1 dove tutto
     * lo stato di pubblicazione viveva nel post).
     */
    public function build(array $selected, ?User $account): array
    {
        $unknown = array_diff(array_keys($selected), array_keys(User::CHANNELS));
        abort_if(!empty($unknown), 422, 'Canale non valido.');

        $accountChannels = $account?->channels ?? [];
        $newsletterProvider = $account?->settings?->newsletterProvider();
        $targetUserId = $account?->id;

        return collect(User::CHANNELS)->keys()->mapWithKeys(function ($id) use ($selected, $accountChannels, $newsletterProvider, $targetUserId) {
            if (!array_key_exists($id, $selected)) {
                return [$id => ['on' => false]];
            }

            $opts = is_array($selected[$id]) ? $selected[$id] : [];

            if (in_array($id, ['facebook', 'instagram', 'linkedin'], true)) {
                // Stesse regole del toggle nel form (SocialOptions.vue), ma
                // applicate qui: l'auto-risposta richiede i commenti attivi e
                // che l'account la consenta su quel canale (reply_on).
                $commentsEnabled = !empty($opts['comments_enabled']);

                return [$id => [
                    'on' => true,
                    'comments_enabled' => $commentsEnabled,
                    'auto_reply_enabled' => $commentsEnabled
                        && !empty($opts['auto_reply_enabled'])
                        && !empty($accountChannels[$id]['reply_on']),
                    'reply_n' => $accountChannels[$id]['reply_n'] ?? null,
                ]];
            }

            if ($id === 'wordpress') {
                $categories = collect($opts['categories'] ?? [])
                    ->filter(fn ($c) => is_array($c) && !empty($c['id']))
                    ->map(fn ($c) => [
                        'id' => (string) $c['id'],
                        'name' => (string) ($c['name'] ?? ''),
                        'on' => !empty($c['on']),
                    ])
                    ->values()
                    ->all();

                return [$id => ['on' => true, 'categories' => $categories]];
            }

            if ($id === 'newsletter') {
                // smtp_custom non ha lista esterna: i destinatari sono i
                // Contact interni, letti direttamente dal worker Python (come
                // fa già con Mailchimp/Brevo) — l'id/url reali li scrive lui
                // una volta inviato davvero, stesso ciclo di vita degli altri
                // provider, nessuno stato aggiuntivo da anticipare qui.
                if ($newsletterProvider === 'smtp_custom') {
                    $tagId = $opts['tag_id'] ?? null;
                    if ($tagId !== null) {
                        $tagId = (int) $tagId;
                        $validTag = $targetUserId && ContactTag::where('id', $tagId)->where('user_id', $targetUserId)->exists();
                        abort_unless($validTag, 422, 'Tag non valido per questo account.');
                    }

                    return [$id => ['on' => true, 'provider' => 'smtp_custom', 'tag_id' => $tagId]];
                }

                $list = $opts['list'] ?? null;
                $list = (is_array($list) && !empty($list['id'])) ? [
                    'provider' => $list['provider'] ?? $newsletterProvider,
                    'id' => (string) $list['id'],
                    'name' => (string) ($list['name'] ?? ''),
                ] : null;

                return [$id => ['on' => true, 'provider' => $newsletterProvider, 'list' => $list]];
            }

            return [$id => ['on' => true]];
        })->all();
    }

    /**
     * Rimette nel payload le chiavi scritte dal worker (id remoti, url,
     * stats...). Il controllo isEditable() blocca già i post con canali
     * pubblicati; questo chiude la finestra in cui il worker pubblica mentre
     * il form è aperto (letto con fresh(), non dal model caricato a inizio
     * richiesta). Un canale spento nel form non eredita nulla.
     */
    public function preserveWorkerKeys(array $channels, array $current): array
    {
        foreach ($channels as $id => $channel) {
            if (empty($channel['on']) || !is_array($current[$id] ?? null)) {
                continue;
            }
            $channels[$id] = array_merge(
                $channel,
                array_intersect_key($current[$id], array_flip(Post::WORKER_CHANNEL_KEYS)),
            );
        }

        return $channels;
    }

    /**
     * Aggregato [commenti abilitati, auto-risposta abilitata] su ALMENO un
     * canale — le due colonne DB restano un riassunto per Posts/Show.vue,
     * la verita' per canale vive nel JSON channels.
     *
     * @return array{0: bool, 1: bool}
     */
    public function commentsAggregate(array $channels): array
    {
        return [
            collect($channels)->contains(fn ($c) => !empty($c['comments_enabled'])),
            collect($channels)->contains(fn ($c) => !empty($c['auto_reply_enabled'])),
        ];
    }
}
