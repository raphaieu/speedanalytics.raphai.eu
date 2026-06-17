<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('speedway_payloads', function (Blueprint $table) {
            $table->id();
            $table->string('source', 32)->default('bbtips');
            $table->string('mode', 64)->nullable();
            $table->text('source_url')->nullable();
            $table->timestamp('captured_at')->nullable()->index();
            $table->string('data_atualizacao', 64)->nullable();
            $table->json('payload');
            $table->json('summary')->nullable();
            $table->string('processing_status', 32)->default('pending')->index();
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('speedway_payloads');
    }
};
