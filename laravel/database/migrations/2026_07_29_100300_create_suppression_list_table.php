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
        // Trasversale per account: controllata prima di ogni invio, a
        // prescindere dal canale/provider newsletter usato.
        Schema::create('suppression_list', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('email');
            $table->enum('reason', ['hard_bounce', 'complaint', 'unsubscribe']);

            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppression_list');
    }
};
