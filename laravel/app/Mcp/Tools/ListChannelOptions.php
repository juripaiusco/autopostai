<?php

namespace App\Mcp\Tools;

use App\Models\Contact;
use App\Models\ContactTag;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_channel_options')]
#[Title('Opzioni canali di un account')]
#[Description("Per un account: canali attivi e se consentono la risposta automatica ai commenti, categorie WordPress, liste newsletter (Mailchimp/Brevo) o tag dei contatti (SMTP). Sono i valori ammessi quando si crea un post. Categorie e liste sono quelle caricate l'ultima volta dall'interfaccia (nessuna chiamata ai servizi esterni).")]
#[IsReadOnly]
class ListChannelOptions extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $request->validate(['account_id' => ['nullable', 'integer']]);

        $account = $this->targetAccount($request);
        $settings = $account->settings;
        $provider = $settings?->newsletterProvider();

        return Response::structured([
            'account' => ['id' => $account->id, 'name' => $account->name],
            'channels' => collect($account->channelsMeta())
                ->map(fn ($c) => ['active' => $c['available'], 'auto_reply_allowed' => $c['replyOn']])
                ->all(),
            'wordpress_categories' => $settings?->wordpress_options['categories'] ?? [],
            'newsletter' => [
                'provider' => $provider,
                'lists' => match ($provider) {
                    'mailchimp' => $settings->nl_mailchimp_options['lists'] ?? [],
                    'brevo' => $settings->nl_brevo_options['lists'] ?? [],
                    default => [],
                },
                'tags' => $provider === 'smtp_custom' ? $this->tags($account) : [],
                'active_contacts' => $provider === 'smtp_custom'
                    ? Contact::where('user_id', $account->id)->where('status', 'active')->count()
                    : null,
            ],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'account_id' => $schema->integer()->description('Id dell\'account (vedi whoami). Default: il tuo.'),
        ];
    }

    /**
     * Stessi conteggi del selettore tag nel form post (contatti attivi).
     */
    private function tags(User $account): array
    {
        return ContactTag::where('user_id', $account->id)
            ->withCount(['contacts as active_contacts' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (ContactTag $t) => ['id' => $t->id, 'name' => $t->name, 'active_contacts' => $t->active_contacts])
            ->all();
    }
}
