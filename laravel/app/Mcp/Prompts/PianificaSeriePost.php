<?php

namespace App\Mcp\Prompts;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('pianifica-serie-post')]
#[Title('Pianifica una serie di post')]
#[Description('Da un tema e un periodo, propone un piano editoriale, lo fa confermare e crea i post programmati.')]
class PianificaSeriePost extends Prompt
{
    /**
     * Crea post: serve un token con permesso di scrittura.
     */
    public function shouldRegister(): bool
    {
        $user = Auth::user();

        return $user instanceof User && (bool) $user->currentMcpToken()?->can('write');
    }

    public function handle(Request $request): Response
    {
        $tema = trim((string) $request->get('tema'));
        $periodo = trim((string) $request->get('periodo'));
        $canali = trim((string) $request->get('canali')) ?: 'quelli attivi sull\'account';
        $account = trim((string) $request->get('account')) ?: 'il mio (o chiedimelo se ne gestisco più di uno)';

        return Response::text(<<<MD
Voglio pianificare una serie di post su FaPer3.

- Tema: {$tema}
- Periodo: {$periodo}
- Canali: {$canali}
- Account: {$account}

Procedi così:
1. `whoami` per capire su quali account posso agire; se l'account non è chiaro, chiedimelo.
2. `list_channel_options` per i canali attivi (e categorie WordPress / liste o tag newsletter), `get_calendar` per i mesi del periodo così da evitare giorni già occupati.
3. Proponimi il piano in una tabella: data e ora, canali, titolo, idea del contenuto (2-3 righe). Distribuisci i post in modo regolare, in orari sensati per il pubblico.
4. Aspetta la mia conferma o le mie modifiche. Non creare nulla prima.
5. Dopo la conferma crea ogni post con `create_post` come programmato (published_at futuro), con un `ai_prompt_post` dettagliato: il testo lo scrive l'AI dell'account con la sua personalità. Se ti chiedo i testi da rivedere subito, usa prima `generate_post_text` e passa il risultato come `ai_content`.
6. Per Instagram serve un'immagine: proponimi `generate_image` o un'immagine da `list_image_archive`.
7. Alla fine riepiloga i post creati con il link di ciascuno.
MD);
    }

    /**
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument('tema', 'Argomento della serie, es. "lancio menu autunnale"', required: true),
            new Argument('periodo', 'Periodo e frequenza, es. "prossime 2 settimane, 3 post a settimana"', required: true),
            new Argument('canali', 'Canali, es. "facebook e instagram" (default: quelli attivi)'),
            new Argument('account', 'Nome o id dell\'account, per admin/manager'),
        ];
    }
}
