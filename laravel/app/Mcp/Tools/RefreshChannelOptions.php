<?php

namespace App\Mcp\Tools;

use App\Services\NewsletterLists;
use App\Services\WordPressCategories;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Name('refresh_channel_options')]
#[Title('Aggiorna categorie WordPress / liste newsletter')]
#[Description('Ricarica dal sito WordPress le categorie, o da Mailchimp/Brevo le liste, di un account (come il pulsante di aggiornamento nel form post). Serve quando list_channel_options non mostra quella che cerchi.')]
#[IsIdempotent]
#[IsOpenWorld]
class RefreshChannelOptions extends Tool
{
    protected string $ability = 'write';

    public function handle(Request $request, WordPressCategories $wordpress, NewsletterLists $newsletter): ResponseFactory
    {
        $data = $request->validate([
            'account_id' => ['nullable', 'integer'],
            'source' => ['required', Rule::in(['wordpress', 'newsletter'])],
        ]);

        $account = $this->targetAccount($request);
        $settings = $account->settings;

        if ($data['source'] === 'wordpress') {
            if (empty($settings?->wordpress_url)) {
                throw ValidationException::withMessages(['source' => 'WordPress non è configurato per questo account.']);
            }

            $categories = $wordpress->fetchAndCache($settings);
            if ($categories === null) {
                throw ValidationException::withMessages(['source' => 'WordPress non ha risposto correttamente. Controlla l\'URL del sito.']);
            }

            return Response::structured(['account_id' => $account->id, 'wordpress_categories' => $categories]);
        }

        [$lists, $error] = $newsletter->fetchAndCache($settings);
        if ($error !== null) {
            throw ValidationException::withMessages(['source' => $error]);
        }

        return Response::structured([
            'account_id' => $account->id,
            'provider' => $settings?->newsletterProvider(),
            'lists' => $lists,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'account_id' => $schema->integer()->description('Account (vedi whoami). Default: il tuo.'),
            'source' => $schema->string()->enum(['wordpress', 'newsletter'])->required(),
        ];
    }
}
