<?php

namespace App\Mcp\Tools;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tool as BaseTool;

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
}
