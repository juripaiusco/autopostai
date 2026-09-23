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
        // Scritto dal worker Python (reply_send) quando la risposta automatica a
        // un commento fallisce: il commento esce dalla coda invece di essere
        // ritentato ogni minuto, rigenerando (e addebitando) la risposta AI.
        Schema::table('comments', function (Blueprint $table) {
            $table->timestamp('reply_failed_at')->nullable()->after('reply_created_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropColumn('reply_failed_at');
        });
    }
};
