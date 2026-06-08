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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->integer('child_on')->nullable()->default(null)->after('name');
            $table->integer('child_max')->nullable()->default(null)->after('child_on');
            $table->integer('notify_read_browser')->nullable()->default(null)->after('child_max');
            $table->integer('notify_read_web')->nullable()->default(null)->after('notify_read_browser');
            $table->json('channels')->nullable()->after('notify_read_web');
            $table->integer('tokens_limit')->nullable()->default(0)->after('channels');
            $table->integer('image_model_limit')->nullable()->default(0)->after('tokens_limit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn([
                'parent_id',
                'child_on',
                'child_max',
                'notify_read_browser',
                'notify_read_web',
                'channels',
                'tokens_limit',
                'image_model_limit',
            ]);
        });
    }
};
