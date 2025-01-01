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

            $table->string('message_id')->unique();
            $table->longText('message')->default('')->nullable();

            $table->string('reply_id')->default(null)->nullable();
            $table->string('reply')->default(null)->nullable();
            $table->string('channel')->default('')->nullable();
            $table->string('from_id')->default('')->nullable();
            $table->string('from_name')->default('')->nullable();
            $table->timestamp('created_time');

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
