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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            // Campo coerente con l'ID della tabella users
            $table->unsignedBigInteger('user_id');
            // Relazione e comportamento in cascata
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->longText('ai_personality')->default(null)->nullable();
            $table->longText('ai_prompt_prefix')->default(null)->nullable();

            $table->string('openai_api_key')->unique()->default(null)->nullable();

            $table->string('meta_page_id')->unique()->default(null)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
