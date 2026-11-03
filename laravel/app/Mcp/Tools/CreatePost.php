<?php

namespace App\Mcp\Tools;

use App\Actions\Posts\CreatePost as CreatePostAction;
use App\Mcp\Tools\Concerns\WritesPosts;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;

#[Name('create_post')]
#[Title('Crea post')]
#[Description(<<<'MD'
Crea un post come bozza (senza published_at) o programmato (published_at futuro). Non pubblica mai subito: per quello c'è publish_post_now.
Il testo: passa ai_content se l'hai già scritto (o generato con generate_post_text), oppure solo ai_prompt_post e FaPer3 lo genera con l'AI dell'account prima dell'uscita.
Prima usa list_channel_options per canali attivi, categorie, liste e tag; get_calendar per evitare sovrapposizioni.
MD)]
class CreatePost extends Tool
{
    use WritesPosts;

    protected string $ability = 'write';

    public function handle(Request $request, CreatePostAction $createPost): ResponseFactory
    {
        $data = $request->validate([
            'account_id' => ['nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:255'],
            ...$this->channelsRules(true),
            'ai_prompt_post' => ['nullable', 'string', 'max:10000'],
            'ai_content' => ['nullable', 'string', 'max:60000'],
            'ai_prompt_comment' => ['nullable', 'string', 'max:5000'],
            'published_at' => $this->publishedAtRules(),
            'archive_images' => ['nullable', 'array', 'max:'.self::MAX_IMAGES],
            'archive_images.*' => ['string'],
            'ai_looks_at_image' => ['nullable', 'boolean'],
        ], $this->publishedAtMessages());

        $me = $this->me();
        $account = $this->targetAccount($request);

        // Admin e manager non pubblicano a proprio nome (come nel form):
        // il post è sempre di un account gestito.
        if ($account->is($me) && ($me->isAdmin() || $me->isManager())) {
            throw ValidationException::withMessages(['account_id' => 'Indica account_id: il post va creato per uno degli account che gestisci (vedi whoami).']);
        }

        if (!empty($data['published_at']) && blank($data['ai_prompt_post'] ?? null) && blank($data['ai_content'] ?? null)) {
            throw ValidationException::withMessages(['ai_content' => 'Un post programmato ha bisogno di ai_content o di ai_prompt_post.']);
        }

        $channels = $this->toFormChannels($data['channels'], $account);
        $files = $this->archiveFiles($account, $data['archive_images'] ?? []);
        $this->ensureInstagramHasImages($channels, count($files));

        [$post, $textQueued] = $this->guard(fn () => $createPost->handle($me, [
            'user_id' => $account->id,
            'title' => $data['title'] ?? '',
            'channels' => $channels,
            'ai_prompt_post' => $data['ai_prompt_post'] ?? null,
            'ai_content' => $data['ai_content'] ?? null,
            'ai_prompt_comment' => $data['ai_prompt_comment'] ?? null,
            'img_ai_check_on' => $data['ai_looks_at_image'] ?? false,
            'published_at' => $data['published_at'] ?? null,
            'action' => 'save',
        ], $files));

        return Response::structured($this->postSummary($post, $textQueued));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'account_id' => $schema->integer()->description('Account del post (vedi whoami). Obbligatorio per admin/manager, ignorato per un utente semplice.'),
            'title' => $schema->string()->description('Titolo interno (e titolo dell\'articolo WordPress / oggetto della newsletter).'),
            'channels' => $this->channelsSchema($schema)->required(),
            'ai_prompt_post' => $schema->string()->description('Istruzioni per l\'AI che scrive il testo.'),
            'ai_content' => $schema->string()->description('Testo definitivo del post. Markdown per WordPress e newsletter.'),
            'ai_prompt_comment' => $schema->string()->description('Istruzioni per le risposte automatiche ai commenti.'),
            'published_at' => $schema->string()->format('date-time')->description('Data/ora di uscita futura (ISO 8601). Ometti per una bozza.'),
            'archive_images' => $schema->array()->items($schema->string())->max(self::MAX_IMAGES)->description('Filename da list_image_archive o get_image_job.'),
            'ai_looks_at_image' => $schema->boolean()->description('L\'AI guarda la prima immagine quando scrive il testo.'),
        ];
    }
}
