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

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->longText('ai_personality')->nullable();
            $table->longText('ai_prompt_prefix')->nullable();
            $table->longText('ai_comment_prefix')->nullable();

            $table->string('openai_api_key')->unique()->nullable();

            $table->string('meta_page_id')->unique()->nullable();

            $table->string('linkedin_person_id')->nullable();
            $table->string('linkedin_company_id')->nullable();
            $table->string('linkedin_client_id')->unique()->nullable();
            $table->string('linkedin_client_secret')->unique()->nullable();
            $table->longText('linkedin_token')->nullable();

            $table->string('wordpress_url')->nullable();
            $table->string('wordpress_username')->nullable();
            $table->string('wordpress_password')->nullable();
            $table->bigInteger('wordpress_cat_id')->nullable();
            $table->longText('wordpress_options')->nullable();

            $table->string('mailchimp_api')->nullable();
            $table->string('mailchimp_datacenter')->nullable();
            $table->string('mailchimp_list_id')->nullable();
            $table->string('mailchimp_from_name')->nullable();
            $table->string('mailchimp_from_email')->nullable();
            $table->longText('mailchimp_template')->nullable();
            $table->longText('mailchimp_template_cta')->nullable();
            $table->longText('mailchimp_options')->nullable();

            $table->string('brevo_api')->nullable();
            $table->string('brevo_list_id')->nullable();
            $table->string('brevo_from_name')->nullable();
            $table->string('brevo_from_email')->nullable();
            $table->longText('brevo_template')->nullable();
            $table->longText('brevo_template_cta')->nullable();
            $table->longText('brevo_options')->nullable();

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
