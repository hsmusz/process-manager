<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('processes', static function (Blueprint $table) {
            $table->string('channel')->default('default')->after('processable_id')->index();
            // TODO: fix index: 1071 Specified key was too long;
//            $table->dropIndex(['process', 'processable_type', 'processable_id']);
//            $table->index(['process', 'processable_type', 'processable_id', 'channel'], 'idx_processes_cmp');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('processes', static function (Blueprint $table) {
            $table->dropIndex(['channel']);
            $table->dropIndex('idx_processes_cmp');
            $table->dropColumn('channel');
            $table->index(['process', 'processable_type', 'processable_id']);
        });
    }
};
