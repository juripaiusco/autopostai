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

            $table->bigInteger('user_id')->unique()->index();

            $table->longText('ai_personality')->default('')->nullable();
            $table->longText('ai_prompt_prefix')->default('')->nullable();

            $table->string('openai_api_key')->default('')->nullable();

            $table->string('meta_page_id')->default('')->nullable();

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
