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

            $table->foreignId('post_id')->constrained()->cascadeOnDelete();

            $table->string('channel');

            $table->string('from_id')->nullable();
            $table->string('from_name')->nullable();

            $table->string('message_id')->nullable();
            $table->longText('message')->nullable();
            $table->timestamp('message_created_time')->nullable();

            $table->string('reply_id')->nullable();
            $table->longText('reply')->nullable();
            $table->timestamp('reply_created_time')->nullable();

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
