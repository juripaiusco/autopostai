<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->renameColumn('mailchimp_api', 'nl_mailchimp_api');
            $table->renameColumn('mailchimp_datacenter', 'nl_mailchimp_datacenter');
            $table->renameColumn('mailchimp_list_id', 'nl_mailchimp_list_id');
            $table->renameColumn('mailchimp_from_name', 'nl_mailchimp_from_name');
            $table->renameColumn('mailchimp_from_email', 'nl_mailchimp_from_email');
            $table->renameColumn('mailchimp_options', 'nl_mailchimp_options');

            $table->renameColumn('brevo_api', 'nl_brevo_api');
            $table->renameColumn('brevo_list_id', 'nl_brevo_list_id');
            $table->renameColumn('brevo_from_name', 'nl_brevo_from_name');
            $table->renameColumn('brevo_from_email', 'nl_brevo_from_email');
            $table->renameColumn('brevo_options', 'nl_brevo_options');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->longText('nl_template')->nullable()->after('nl_mailchimp_options');
            $table->longText('nl_template_cta')->nullable()->after('nl_template');
        });

        DB::table('settings')->whereNotNull('mailchimp_template')->update([
            'nl_template' => DB::raw('mailchimp_template'),
        ]);
        DB::table('settings')->whereNull('nl_template')->whereNotNull('brevo_template')->update([
            'nl_template' => DB::raw('brevo_template'),
        ]);
        DB::table('settings')->whereNotNull('mailchimp_template_cta')->update([
            'nl_template_cta' => DB::raw('mailchimp_template_cta'),
        ]);
        DB::table('settings')->whereNull('nl_template_cta')->whereNotNull('brevo_template_cta')->update([
            'nl_template_cta' => DB::raw('brevo_template_cta'),
        ]);

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['mailchimp_template', 'mailchimp_template_cta', 'brevo_template', 'brevo_template_cta']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->longText('mailchimp_template')->nullable()->after('nl_mailchimp_from_email');
            $table->longText('mailchimp_template_cta')->nullable()->after('mailchimp_template');
            $table->longText('brevo_template')->nullable()->after('nl_brevo_from_email');
            $table->longText('brevo_template_cta')->nullable()->after('brevo_template');
        });

        DB::table('settings')->whereNotNull('nl_template')->update([
            'mailchimp_template' => DB::raw('nl_template'),
            'brevo_template' => DB::raw('nl_template'),
        ]);
        DB::table('settings')->whereNotNull('nl_template_cta')->update([
            'mailchimp_template_cta' => DB::raw('nl_template_cta'),
            'brevo_template_cta' => DB::raw('nl_template_cta'),
        ]);

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['nl_template', 'nl_template_cta']);
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->renameColumn('nl_mailchimp_api', 'mailchimp_api');
            $table->renameColumn('nl_mailchimp_datacenter', 'mailchimp_datacenter');
            $table->renameColumn('nl_mailchimp_list_id', 'mailchimp_list_id');
            $table->renameColumn('nl_mailchimp_from_name', 'mailchimp_from_name');
            $table->renameColumn('nl_mailchimp_from_email', 'mailchimp_from_email');
            $table->renameColumn('nl_mailchimp_options', 'mailchimp_options');

            $table->renameColumn('nl_brevo_api', 'brevo_api');
            $table->renameColumn('nl_brevo_list_id', 'brevo_list_id');
            $table->renameColumn('nl_brevo_from_name', 'brevo_from_name');
            $table->renameColumn('nl_brevo_from_email', 'brevo_from_email');
            $table->renameColumn('nl_brevo_options', 'brevo_options');
        });
    }
};
