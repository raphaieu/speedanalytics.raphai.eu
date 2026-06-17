<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collector_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('source', 32)->default('bbtips')->unique();
            $table->string('status', 32)->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_payload_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->text('last_error_message')->nullable();
            $table->string('last_external_id', 64)->nullable();
            $table->timestamp('last_data_updated_at')->nullable();
            $table->boolean('needs_login')->default(false);
            $table->json('metadata_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collector_statuses');
    }
};
