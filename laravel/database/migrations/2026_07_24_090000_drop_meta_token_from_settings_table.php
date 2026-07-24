<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Il token Meta non e' piu' per-account: e' un token globale unico, gestito
     * dall'amministratore in python/.env (la propria app Meta condivisa alle
     * pagine via Business Manager), come in v1 (META_USER_ACCESS_TOKEN). La
     * colonna settings.meta_token, aggiunta per il modello BYOK poi abbandonato,
     * non serve piu'.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('meta_token');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->longText('meta_token')->nullable()->after('meta_page_id');
        });
    }
};
