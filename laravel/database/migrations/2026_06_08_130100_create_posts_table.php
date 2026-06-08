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

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Chi ha creato il post: non necessariamente lo stesso utente
            // a cui il post appartiene (gerarchia manager/sub-utenti).
            $table->unsignedBigInteger('created_by_user_id');

            $table->string('title');

            // Istruzioni e risposta del LLM
            $table->longText('ai_prompt_post')->nullable();
            $table->longText('ai_content')->nullable();
            $table->longText('ai_prompt_comment')->nullable();

            $table->longText('img')->nullable();

            // Flag: indica al LLM se leggere o meno l'immagine
            $table->string('img_ai_check_on', 1)->default(0);

            // Canali di comunicazione del post (snapshot delle impostazioni
            // utente al momento della creazione: ogni post può avere opzioni
            // proprie, indipendenti da modifiche successive dell'utente).
            $table->json('channels');

            $table->string('preview', 1)->default(0);

            $table->timestamp('published_at')->nullable();
            $table->string('published', 1)->default(0);

            // Flag: il post ha raggiunto il limite di commenti configurato
            // per i canali, lo script Python smette di esaminarlo.
            $table->string('task_complete', 1)->default(0);

            $table->integer('check_attempts')->default(0);
            $table->timestamp('on_hold_until')->nullable();

            // Stato di sincronizzazione del post sui canali esterni:
            // 0 = non modificato, 1 = modificato, 2 = in attesa di sync
            $table->string('updated', 1)->default(0);

            // Stato di eliminazione sui canali esterni (separato dal soft delete)
            $table->string('deleted', 1)->default(0);

            $table->softDeletes();
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
