<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collector_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 32)->default('bbtips');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('status', 32)->nullable();
            $table->unsignedInteger('payload_count')->default(0);
            $table->unsignedInteger('race_count')->default(0);
            $table->unsignedInteger('pending_count')->default(0);
            $table->unsignedInteger('settled_count')->default(0);
            $table->text('error_message')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collector_runs');
    }
};
