<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Piu' account possono condividere volutamente la stessa app LinkedIn
     * (client_id/secret) per evitare di dover ri-autorizzare il token per
     * ognuno: quando l'admin/manager aggiorna il token, si propaga a tutti
     * gli account collegati alla stessa app (vedi LinkedInController).
     * Il vincolo unique() sui due campi lo impediva a livello di DB.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // Colonne, non nomi indice letterali: con DB_PREFIX impostato
            // (es. DB su hosting condiviso) il nome reale dell'indice include
            // il prefisso (Blueprint::createIndexName()) e un confronto per
            // stringa fissa fallirebbe silenziosamente.
            if (Schema::hasIndex('settings', ['linkedin_client_id'], 'unique')) {
                $table->dropUnique(['linkedin_client_id']);
            }

            if (Schema::hasIndex('settings', ['linkedin_client_secret'], 'unique')) {
                $table->dropUnique(['linkedin_client_secret']);
            }

            $table->timestamp('linkedin_token_expires_at')->nullable()->after('linkedin_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('linkedin_token_expires_at');
            $table->unique('linkedin_client_id');
            $table->unique('linkedin_client_secret');
        });
    }
};
