<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Formato di ai_content (markdown = testo AI di sempre, html = editor
     * ricco di WordPress/newsletter, text = solo social) e testo social
     * personalizzato quando il post ha anche un canale articolo (vuoto = il
     * worker lo ricava dall'articolo).
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('ai_content_format', 16)->default('markdown')->after('ai_content');
            $table->longText('ai_content_social')->nullable()->after('ai_content_format');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['ai_content_format', 'ai_content_social']);
        });
    }
};
