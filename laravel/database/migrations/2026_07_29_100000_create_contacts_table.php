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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('email');
            $table->enum('status', ['unverified', 'active', 'bounced', 'unsubscribed'])
                ->default('unverified');

            // Provenienza del consenso (es. api, import, manuale) e prova GDPR.
            $table->string('consent_source')->nullable();
            $table->string('consent_ip')->nullable();
            $table->timestamp('consent_at')->nullable();

            $table->timestamp('unsubscribed_at')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->unique(['user_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
