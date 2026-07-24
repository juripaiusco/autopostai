<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Discrimina il tipo di notifica in coda cosi' che il comando send-pending sappia
     * quale classe Notification istanziare:
     *  - 'alert'          : notifica di prodotto/marketing, canale database => visibile in campanella (default, comportamento storico).
     *  - 'post_published' : notifica funzionale "post inviato", solo WebPush => NON in campanella.
     * Le righe 'post_published' le inserisce il worker Python dopo aver pubblicato un post.
     */
    public function up(): void
    {
        Schema::table('push_notifications', function (Blueprint $table) {
            $table->string('kind')->default('alert')->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('push_notifications', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
