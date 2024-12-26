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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('user_id')->index();

            $table->string('title');
            $table->longText('ai_prompt_post')->default('')->nullable();
            $table->string('img')->default('')->nullable();
            $table->string('img_ai_check_on', 1)->default(0)->nullable();

            $table->string('meta_facebook_on', 1)->default(0)->nullable();
            $table->string('meta_facebook_id')->default(0)->nullable();
            $table->string('meta_instagram_on', 1)->default(0)->nullable();
            $table->string('meta_instagram_id')->default(0)->nullable();
            $table->string('wordpress_on', 1)->default(0)->nullable();
            $table->string('wordpress_id')->default(0)->nullable();
            $table->string('newsletter_on', 1)->default(0)->nullable();
            $table->string('newsletter_id')->default(0)->nullable();

            $table->timestamp('published_at')->nullable();
            $table->string('published', 1)->default(0)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
