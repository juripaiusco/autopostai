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
        // Dettaglio per singolo contatto/singolo post. Le statistiche
        // aggregate per post vivono in posts.channels.newsletter.stats.
        Schema::create('email_sends', function (Blueprint $table) {
            $table->id();

            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();

            $table->enum('status', ['queued', 'sent', 'delivered', 'opened', 'clicked', 'bounced'])
                ->default('queued');

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_sends');
    }
};
