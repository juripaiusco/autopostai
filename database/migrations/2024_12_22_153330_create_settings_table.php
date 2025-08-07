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
            $table->longText('ai_comment_prefix')->default(null)->nullable();

            $table->string('openai_api_key')->unique()->default(null)->nullable();

            $table->string('meta_page_id')->unique()->default(null)->nullable();

            $table->string('linkedin_person_id')->default(null)->nullable();
            $table->string('linkedin_company_id')->default(null)->nullable();
            $table->string('linkedin_client_id')->unique()->default(null)->nullable();
            $table->string('linkedin_client_secret')->unique()->default(null)->nullable();
            $table->longText('linkedin_token')->default(null)->nullable();

            $table->string('wordpress_url')->default(null)->nullable();
            $table->string('wordpress_username')->default(null)->nullable();
            $table->string('wordpress_password')->default(null)->nullable();
            $table->bigInteger('wordpress_cat_id')->default(null)->nullable();
            $table->longText('wordpress_options')->default(null)->nullable();

            $table->string('mailchimp_api')->default(null)->nullable();
            $table->string('mailchimp_datacenter')->default(null)->nullable();
            $table->string('mailchimp_list_id')->default(null)->nullable();
            $table->string('mailchimp_from_name')->default(null)->nullable();
            $table->string('mailchimp_from_email')->default(null)->nullable();
            $table->longText('mailchimp_template')->default(null)->nullable();
            $table->longText('mailchimp_template_cta')->default(null)->nullable();
            $table->longText('mailchimp_options')->default(null)->nullable();

            $table->string('brevo_api')->default(null)->nullable();
            $table->string('brevo_list_id')->default(null)->nullable();
            $table->string('brevo_from_name')->default(null)->nullable();
            $table->string('brevo_from_email')->default(null)->nullable();
            $table->longText('brevo_template')->default(null)->nullable();
            $table->longText('brevo_template_cta')->default(null)->nullable();
            $table->longText('brevo_options')->default(null)->nullable();

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
