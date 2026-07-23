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
            $table->string('nl_smtp_host')->nullable();
            $table->string('nl_smtp_port')->nullable();
            $table->string('nl_smtp_username')->nullable();
            $table->string('nl_smtp_password')->nullable();
            $table->string('nl_smtp_encryption')->nullable();
            $table->string('nl_smtp_sender')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['nl_smtp_host', 'nl_smtp_port', 'nl_smtp_username', 'nl_smtp_password', 'nl_smtp_encryption', 'nl_smtp_sender']);
        });
    }
};
