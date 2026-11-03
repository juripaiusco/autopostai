<?php

namespace App\Http\Controllers;

use App\Models\McpToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class McpTokenController extends Controller
{
    /**
     * Nuovo token MCP per l'utente collegato (solo per sé: il token agisce
     * con i suoi permessi). Il plaintext si vede una volta sola: flash di un
     * giro come la API key contatti, SettingsController::edit lo passa alla view.
     */
    public function store(Request $request): RedirectResponse
    {
        $me = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'ability' => ['required', Rule::in(array_keys(McpToken::ABILITIES))],
            'expiry_days' => ['required', 'integer', Rule::in(McpToken::EXPIRY_DAYS)],
        ]);

        if ($me->mcpTokens()->active()->count() >= McpToken::MAX_ACTIVE_PER_USER) {
            return back()->withErrors(['name' => 'Hai già '.McpToken::MAX_ACTIVE_PER_USER.' token attivi: revocane uno prima di crearne un altro.']);
        }

        [, $plaintext] = McpToken::issue($me, $data['name'], [$data['ability']], (int) $data['expiry_days']);

        return back()
            ->with('mcp_token_plaintext', $plaintext)
            ->with('toast', 'Token MCP creato');
    }

    /**
     * Revoca (non cancella): la riga resta per lo storico e per il log MCP.
     */
    public function destroy(Request $request, McpToken $token): RedirectResponse
    {
        abort_unless($token->user_id === $request->user()->id, 404);

        $token->update(['revoked_at' => $token->revoked_at ?? now()]);

        return back()->with('toast', 'Token MCP revocato');
    }
}
