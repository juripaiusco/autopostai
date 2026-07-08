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
     * Archivio delle notifiche push: 'user_id' per un destinatario specifico,
     * oppure 'audience' ('all' = tutti, solo admin | 'children' = i propri
     * sotto-utenti) per un invio di massa. Esattamente uno dei due e' valorizzato.
     * L'invio vero (WebPush) lo fa il comando schedulato notifications:send-pending,
     * non questo controller — vedi SendPendingPushNotifications.
     */
    public function up(): void
    {
        Schema::create('push_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('audience')->nullable(); // 'all' | 'children' | null (se user_id e' valorizzato)
            $table->string('title');
            $table->text('body');
            $table->string('url')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('recipients_count')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('push_notifications');
    }
};
