<?php

namespace App\Mcp\Tools;

use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tool as BaseTool;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Base dei tool MCP di FaPer3. Ogni tool:
 *  - dichiara il permesso del token che richiede ($ability): senza, il tool
 *    non compare nell'elenco e non è invocabile (laravel/mcp applica
 *    shouldRegister() anche a tools/call);
 *  - valida i propri argomenti con $request->validate() (laravel/mcp non
 *    applica lo schema JSON lato server);
 *  - autorizza con le Policy/i metodi di User usati dall'interfaccia;
 *  - restituisce array costruiti campo per campo, mai toArray() di un model
 *    (Settings contiene chiavi in chiaro).
 */
abstract class Tool extends BaseTool
{
    /** Permesso richiesto al token: read | write | publish (McpToken::ABILITIES). */
    protected string $ability = 'read';

    public function shouldRegister(): bool
    {
        return (bool) $this->me()?->currentMcpToken()?->can($this->ability);
    }

    protected function me(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    /**
     * Argomento account_id comune: per admin/manager restringe la vista a un
     * account gestito (equivalente MCP dello scope scelto nella sidebar).
     */
    protected function accountIdSchema(JsonSchema $schema): Type
    {
        return $schema->integer()
            ->description('Id di un account gestito (vedi whoami). Ometti per tutti gli account visibili.');
    }

    /**
     * account_id → id per Post/Contact::visibleTo. Stessa regola dello scope
     * di sessione (User::resolveScopedUser), con due differenze: un id non
     * consentito è un errore (in UI veniva ignorato in silenzio) e il proprio
     * id è sempre ammesso.
     */
    protected function scopedAccountId(Request $request): ?int
    {
        $me = $this->me();
        $id = $request->get('account_id');

        if ($id === null || $id === '') {
            return null;
        }

        $id = (int) $id;

        if ($id === $me->id) {
            return ($me->isAdmin() || $me->isManager()) ? $me->id : null;
        }

        if (!$me->resolveScopedUser($id)) {
            throw ValidationException::withMessages(['account_id' => 'Account non trovato o non gestito da te.']);
        }

        return $id;
    }

    /**
     * Account su cui agire (default: sé stessi), verificato con
     * User::canActFor come gli endpoint del form post.
     */
    protected function targetAccount(Request $request): User
    {
        $me = $this->me();
        $id = $request->get('account_id');

        if ($id === null || $id === '' || (int) $id === $me->id) {
            return $me;
        }

        $target = User::find((int) $id);

        if (!$target || !$me->canActFor($target)) {
            throw ValidationException::withMessages(['account_id' => 'Account non trovato o non gestito da te.']);
        }

        return $target;
    }

    /**
     * Post visibile all'utente e autorizzato per $ability (PostPolicy).
     * Stesso messaggio per inesistente e non visibile: non si rivela
     * l'esistenza dei post di altri account.
     */
    protected function findPost(mixed $id, string $ability = 'view'): Post
    {
        $post = Post::find((int) $id);

        if (!$post || Gate::denies('view', $post)) {
            throw ValidationException::withMessages(['post_id' => 'Post non trovato.']);
        }

        if ($ability !== 'view' && Gate::denies($ability, $post)) {
            throw ValidationException::withMessages(['post_id' => 'Non hai i permessi per questa operazione su questo post.']);
        }

        return $post;
    }

    /**
     * Esegue un'Action condivisa con l'interfaccia: i suoi abort() (422/403
     * con messaggio per l'utente) diventano errori leggibili dall'LLM invece
     * di un "errore interno" generico di laravel/mcp.
     *
     * @template T
     *
     * @param  callable(): T  $action
     * @return T
     */
    protected function guard(callable $action): mixed
    {
        try {
            return $action();
        } catch (HttpExceptionInterface $e) {
            throw ValidationException::withMessages(['error' => $e->getMessage() ?: 'Operazione non consentita.']);
        }
    }
}
