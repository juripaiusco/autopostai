<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Lista post (ordinata per data), conteggi per stato, coda del worker
        // (published=0 e published_at scaduto) e metriche della dashboard
        // (post e commenti degli ultimi 12 mesi): senza indici, scansione
        // completa delle tabelle a ogni pagina con i dati di produzione.
        Schema::table('posts', function (Blueprint $table) {
            $table->index('published_at');
            $table->index(['published', 'published_at']);
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->index('message_created_time');
            $table->index('reply_created_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['published_at']);
            $table->dropIndex(['published', 'published_at']);
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex(['message_created_time']);
            $table->dropIndex(['reply_created_time']);
        });
    }
};
