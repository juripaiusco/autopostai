<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Conteggi per stato degli invii di un post (WHERE post_id GROUP BY status):
     * li fanno il worker a ogni batch (stats_for_post) e Laravel a ogni apertura
     * del pixel (Post::refreshNewsletterStats). Con l'indice composito il
     * conteggio si risolve sull'indice, senza leggere le righe — con liste da
     * migliaia di contatti per post fa la differenza a ogni giro.
     */
    public function up(): void
    {
        Schema::table('email_sends', function (Blueprint $table) {
            $table->index(['post_id', 'status']);
        });

        // L'indice implicito della FK su post_id InnoDB lo elimina da solo; se
        // invece è esplicito (ricreato da un down() precedente) resta doppione.
        if (Schema::hasIndex('email_sends', 'email_sends_post_id_foreign')) {
            Schema::table('email_sends', function (Blueprint $table) {
                $table->dropIndex('email_sends_post_id_foreign');
            });
        }
    }

    /**
     * InnoDB, creando l'indice composito (che inizia con post_id), elimina da
     * solo l'indice implicito della foreign key su post_id: va ricreato prima,
     * altrimenti il drop fallisce con "needed in a foreign key constraint".
     */
    public function down(): void
    {
        $hasFkIndex = Schema::hasIndex('email_sends', 'email_sends_post_id_foreign');

        Schema::table('email_sends', function (Blueprint $table) use ($hasFkIndex) {
            if (!$hasFkIndex) {
                $table->index('post_id', 'email_sends_post_id_foreign');
            }
            $table->dropIndex(['post_id', 'status']);
        });
    }
};
