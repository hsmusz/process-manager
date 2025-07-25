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
        Schema::create('processes', function (Blueprint $table) {
            $table->id();
            $table->string('process');
            $table->string('processable_type');
            $table->string('processable_id');
            $table->string('status');
            $table->json('meta')->default('{}');
            $table->unsignedInteger('version')->default(0);
            $table->unsignedInteger('attempts')->default(0);
            $table->dateTimeTz('retry_after')->nullable();
            $table->timestampsTz();
        });

        Schema::create('process_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('process_id');
            $table->foreign('process_id')->references('id')->on('processes')->onDelete('cascade');
            $table->string('step');
            $table->string('status');
            $table->text('message');
            $table->longText('details');
            $table->longText('logs')->nullable();
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('process_steps');
        Schema::dropIfExists('processes');
    }
};
