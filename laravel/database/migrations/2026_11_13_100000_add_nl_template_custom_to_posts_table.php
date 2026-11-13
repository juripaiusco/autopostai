<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Template HTML della newsletter scelto per il singolo post: sostituisce
     * settings.nl_template dell'account (vuoto = template account). Senza
     * [content] è un'email scritta per intero dall'utente, senza testo AI.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->longText('nl_template_custom')->nullable()->after('ai_content_social');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('nl_template_custom');
        });
    }
};
