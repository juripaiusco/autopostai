<?php

namespace App\Mcp\Tools\Concerns;

use App\Models\Post;
use App\Models\User;
use App\Services\ImageArchive;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Traduzione tra il formato "per LLM" dei tool create_post/update_post e il
 * payload che CreatePost/UpdatePost (le stesse Action del form) si aspettano.
 */
trait WritesPosts
{
    private const SOCIAL_CHANNELS = ['facebook', 'instagram', 'linkedin'];

    /** Massimo immagini d'archivio per chiamata. */
    private const MAX_IMAGES = 10;

    /**
     * Canali in formato semplice: id di categorie e liste invece degli
     * oggetti completi del form.
     */
    protected function channelsSchema(JsonSchema $schema): mixed
    {
        $social = fn () => $schema->object([
            'comments_enabled' => $schema->boolean()->description('Raccogli i commenti del post.'),
            'auto_reply_enabled' => $schema->boolean()->description("Risposta automatica dell'AI ai commenti (solo se l'account la consente sul canale)."),
        ]);

        return $schema->object([
            'facebook' => $social(),
            'instagram' => $social()->description('Richiede almeno una immagine.'),
            'linkedin' => $social(),
            'wordpress' => $schema->object([
                'category_ids' => $schema->array()->items($schema->string())->description('Id da list_channel_options.wordpress_categories.'),
            ]),
            'newsletter' => $schema->object([
                'list_id' => $schema->string()->description('Mailchimp/Brevo: id da list_channel_options.newsletter.lists.'),
                'tag_id' => $schema->integer()->description('SMTP: invia solo ai contatti con questo tag (id da list_channel_options.newsletter.tags). Ometti per tutti i contatti attivi.'),
            ]),
        ])->description('Canali su cui pubblicare: una chiave per canale (oggetto vuoto = opzioni di default). Solo canali attivi sull\'account.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function channelsRules(bool $required): array
    {
        return [
            'channels' => [$required ? 'required' : 'sometimes', 'array', 'min:1'],
            'channels.*' => ['nullable'],
            'channels.*.category_ids' => ['sometimes', 'array'],
            'channels.*.category_ids.*' => ['string'],
            'channels.*.list_id' => ['sometimes', 'nullable', 'string'],
            'channels.*.tag_id' => ['sometimes', 'nullable', 'integer'],
            'channels.*.comments_enabled' => ['sometimes', 'boolean'],
            'channels.*.auto_reply_enabled' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Canali dell'input LLM → formato del form (vedi PostChannels::build).
     * Categorie e liste si risolvono sulla cache dell'account: un id
     * sconosciuto è un errore esplicito, non un valore inventato.
     */
    protected function toFormChannels(array $input, User $account): array
    {
        $meta = $account->channelsMeta();
        $settings = $account->settings;

        $out = [];
        foreach ($input as $channel => $opts) {
            $opts = is_array($opts) ? $opts : [];

            if (!isset($meta[$channel])) {
                throw ValidationException::withMessages(['channels' => "Canale \"{$channel}\" sconosciuto. Validi: ".implode(', ', array_keys($meta)).'.']);
            }
            if (!$meta[$channel]['available']) {
                throw ValidationException::withMessages(['channels' => "Il canale {$channel} non è attivo su questo account."]);
            }

            $out[$channel] = match (true) {
                in_array($channel, self::SOCIAL_CHANNELS, true) => [
                    'comments_enabled' => (bool) ($opts['comments_enabled'] ?? false),
                    'auto_reply_enabled' => (bool) ($opts['auto_reply_enabled'] ?? false),
                ],
                $channel === 'wordpress' => ['categories' => $this->wordpressCategories($opts['category_ids'] ?? [], $settings?->wordpress_options['categories'] ?? [])],
                $channel === 'newsletter' => $this->newsletterOptions($opts, $account),
                default => [],
            };
        }

        return $out;
    }

    private function wordpressCategories(array $ids, array $cached): array
    {
        $byId = collect($cached)->keyBy(fn ($c) => (string) $c['id']);

        return collect($ids)->map(function ($id) use ($byId) {
            $category = $byId->get((string) $id);
            if (!$category) {
                throw ValidationException::withMessages(['channels' => "Categoria WordPress {$id} non trovata: aggiorna l'elenco con refresh_channel_options."]);
            }

            return ['id' => (string) $category['id'], 'name' => $category['name'], 'on' => true];
        })->values()->all();
    }

    private function newsletterOptions(array $opts, User $account): array
    {
        $provider = $account->settings?->newsletterProvider();

        if ($provider === 'smtp_custom') {
            return ['tag_id' => $opts['tag_id'] ?? null];
        }

        if (empty($opts['list_id'])) {
            throw ValidationException::withMessages(['channels' => 'Newsletter: indica list_id (vedi list_channel_options).']);
        }

        $lists = match ($provider) {
            'mailchimp' => $account->settings->nl_mailchimp_options['lists'] ?? [],
            'brevo' => $account->settings->nl_brevo_options['lists'] ?? [],
            default => [],
        };
        $list = collect($lists)->first(fn ($l) => (string) $l['id'] === (string) $opts['list_id']);

        if (!$list) {
            throw ValidationException::withMessages(['channels' => "Lista newsletter {$opts['list_id']} non trovata: aggiorna l'elenco con refresh_channel_options."]);
        }

        return ['list' => ['provider' => $provider, 'id' => (string) $list['id'], 'name' => $list['name']]];
    }

    /**
     * Canali attuali del post nel formato del form, per gli aggiornamenti
     * parziali (update_post senza channels).
     */
    protected function currentFormChannels(Post $post): array
    {
        return collect($post->channels ?? [])
            ->filter(fn ($c) => is_array($c) && !empty($c['on']))
            ->all();
    }

    /**
     * Immagini dell'archivio AI → file da allegare, come fa il form (che le
     * scarica e le ricarica): così passano dallo stesso salvataggio
     * (estensione dal contenuto, cartella posts/{id}).
     *
     * @param  array<int, string>  $filenames
     * @return array<int, UploadedFile>
     */
    protected function archiveFiles(User $account, array $filenames): array
    {
        $archive = app(ImageArchive::class);

        return collect($filenames)->unique()->map(function (string $filename) use ($archive, $account) {
            $path = $archive->path($account, $this->me(), $filename);
            if (!$path) {
                throw ValidationException::withMessages(['archive_images' => "Immagine \"{$filename}\" non trovata nell'archivio (vedi list_image_archive)."]);
            }

            return new UploadedFile(Storage::disk('public')->path($path), $filename, null, null, true);
        })->values()->all();
    }

    /**
     * Data di uscita per un token "write": solo futura. Una data passata farebbe
     * uscire il post al giro successivo del worker, cioè una pubblicazione
     * immediata, riservata al tool publish_post_now (permesso "publish").
     */
    protected function publishedAtRules(): array
    {
        return ['nullable', 'date', 'after:now'];
    }

    /**
     * @return array<string, string>
     */
    protected function publishedAtMessages(): array
    {
        return ['published_at.after' => 'published_at deve essere nel futuro. Per pubblicare subito usa publish_post_now (serve il permesso di pubblicazione).'];
    }

    protected function ensureInstagramHasImages(array $channels, int $imageCount): void
    {
        if (array_key_exists('instagram', $channels) && $imageCount === 0) {
            throw ValidationException::withMessages(['channels' => 'Instagram richiede almeno una immagine: aggiungine una con archive_images (vedi generate_image / list_image_archive).']);
        }
    }

    protected function postSummary(Post $post, bool $textQueued = false): array
    {
        $post->refresh();

        return [
            'id' => $post->id,
            'title' => $post->title,
            'account_id' => $post->user_id,
            'status' => $post->status(),
            'published_at' => $post->published_at?->toIso8601String(),
            'channels' => $post->activeChannels(),
            'images' => count($post->img ?? []),
            'has_text' => trim((string) $post->ai_content) !== '',
            'text_queued' => $textQueued,
            'url' => route('posts.show', $post),
            'edit_url' => route('posts.edit', $post),
        ];
    }
}
