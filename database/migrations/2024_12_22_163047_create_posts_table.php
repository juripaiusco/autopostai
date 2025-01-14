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

            // Campo coerente con l'ID della tabella users
            $table->unsignedBigInteger('user_id');
            // Relazione e comportamento in cascata
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Titolo del post - uso interno
            $table->string('title');

            // Istruzioni da inviare al LLM
            $table->longText('ai_prompt_post')->default('')->nullable();

            // Risposta del LLM
            $table->longText('ai_content')->default('')->nullable();

            // Immagine del post
            $table->string('img')->default('')->nullable();

            // Check per dire al LLM se leggere o meno l'immagine
            $table->string('img_ai_check_on', 1)->default(0)->nullable();

            // Lista dei canali di comunicazione, questa lista è dettata dall'utente
            // se all'utente vengono cambiate impostazioni questo campo rimane invariato,
            // ogni post potrà così avere opzioni capillari, cioè un post può ricevere risposte
            // un altro post ha le risposte chiuse.
            $table->json('channels')->nullable();

            // Data pubblicazione del post
            $table->timestamp('published_at')->nullable();

            // Flag se il post è pubblicato
            $table->string('published', 1)->default(0)->nullable();

            // Flag se il post ha concluso le risposte, nel campo channel vengono impostate le opzioni
            // del post, una di queste è la replica e il numero di repliche, che poi vengono inserite
            // nella tabella dei commenti. Una volta raggiunto il numero di risposte indicate il post
            // si può considerare "replied", cioè ha risposto al numero massimo di commenti, così non
            // verrà più esaminato dallo script Python e non farà più chiamate API ai vari canali.
            $table->string('replied', 1)->default(0)->nullable();

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
