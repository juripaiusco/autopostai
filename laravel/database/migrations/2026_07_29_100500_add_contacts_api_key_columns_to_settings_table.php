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
        Schema::table('settings', function (Blueprint $table) {
            // Hash a riposo (stesso principio di Sanctum): il plaintext si
            // vede una sola volta al momento della generazione, mai più letto.
            $table->string('contacts_api_key_hash')->nullable()->unique()->after('nl_template_cta');
            $table->string('contacts_api_key_last_four', 4)->nullable()->after('contacts_api_key_hash');
            $table->timestamp('contacts_api_key_created_at')->nullable()->after('contacts_api_key_last_four');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'contacts_api_key_hash',
                'contacts_api_key_last_four',
                'contacts_api_key_created_at',
            ]);
        });
    }
};
