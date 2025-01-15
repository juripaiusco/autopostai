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
        Schema::create('comments', function (Blueprint $table) {
            $table->id();

            // Campo coerente con l'ID della tabella users
            $table->unsignedBigInteger('post_id');
            // Relazione e comportamento in cascata
            $table->foreign('post_id')->references('id')->on('posts')->onDelete('cascade');

            $table->string('channel')->default('')->nullable();

            $table->string('message_id')->default('')->nullable();
            $table->longText('message')->default('')->nullable();

            $table->string('reply_id')->default(null)->nullable();
            $table->longText('reply')->default(null)->nullable();

            $table->string('from_id')->default('')->nullable();
            $table->string('from_name')->default('')->nullable();
            $table->timestamp('message_created_time')->default(null)->nullable();
            $table->timestamp('reply_created_time')->default(null)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
