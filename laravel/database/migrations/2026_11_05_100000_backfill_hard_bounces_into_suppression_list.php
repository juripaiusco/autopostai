<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A7: fino al 05/11 il worker segnava i contatti `bounced` senza mettere
     * l'indirizzo in suppression list, quindi dalla scheda si potevano
     * riattivare. Aggiunge la riga `hard_bounce` per ogni contatto già
     * `bounced` (anche eliminato) che non ce l'ha. Righe esistenti (es.
     * `unsubscribe`, `complaint`) non toccate. Rilanciarla non fa danni.
     */
    public function up(): void
    {
        DB::table('suppression_list')->insertUsing(
            ['user_id', 'email', 'reason', 'created_at'],
            DB::table('contacts as c')
                ->select('c.user_id', 'c.email', DB::raw("'hard_bounce'"), DB::raw('CURRENT_TIMESTAMP'))
                ->where('c.status', 'bounced')
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('suppression_list as sl')
                    ->whereColumn('sl.user_id', 'c.user_id')
                    ->whereColumn('sl.email', 'c.email'))
        );
    }

    /**
     * Niente da annullare: le righe aggiunte non si distinguono da quelle
     * scritte dal worker, e toglierle riaprirebbe il bug.
     */
    public function down(): void
    {
    }
};
