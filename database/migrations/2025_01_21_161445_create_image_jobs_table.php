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
        Schema::create('image_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('pending'); // pending, running, completed, failed
            $table->string('image_path')->nullable();     // Percorso immagine generata
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('image_jobs');
    }
};
