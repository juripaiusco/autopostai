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
        Schema::table('image_jobs', function (Blueprint $table) {
            // Chi ha lanciato la generazione: non necessariamente lo stesso
            // utente a cui l'immagine appartiene (user_id), quando un
            // admin/manager genera per un sotto-account (stesso pattern di
            // posts.created_by_user_id).
            $table->foreignId('created_by_user_id')->nullable()->after('user_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('image_jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user_id');
        });
    }
};
