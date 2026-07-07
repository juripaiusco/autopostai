<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
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
            $table->dropUnique('settings_linkedin_client_id_unique');
            $table->dropUnique('settings_linkedin_client_secret_unique');
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
