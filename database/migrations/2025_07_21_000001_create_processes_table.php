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
            $table->string('type');
            $table->string('status');
            $table->json('meta');
            $table->unsignedInteger('version')->default(0);
            $table->unsignedInteger('attempts')->default(0);
            $table->dateTimeTz('retry_at')->nullable();
            $table->timestamps();
        });

        Schema::create('process_steps', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('status');
            $table->json('meta');
            $table->unsignedInteger('version')->default(0);
            $table->unsignedInteger('attempts')->default(0);
            $table->dateTimeTz('retry_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processes');
    }
};
