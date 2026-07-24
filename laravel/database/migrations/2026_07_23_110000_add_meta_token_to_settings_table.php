<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Token di accesso Meta (Facebook/Instagram) per-account, modello BYOK come
     * openai_api_key: il worker Python di pubblicazione lo legge da qui per pubblicare
     * sulla pagina indicata in meta_page_id. L'IG business id resta risolto a runtime
     * dalla pagina, quindi nessuna colonna dedicata.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->longText('meta_token')->nullable()->after('meta_page_id');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('meta_token');
        });
    }
};
