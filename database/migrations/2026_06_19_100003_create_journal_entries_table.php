<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('demo_operation_id')->constrained()->cascadeOnDelete();
            $table->text('note');
            $table->string('emotion', 64)->nullable();
            $table->unsignedTinyInteger('confidence_level')->nullable();
            $table->unsignedTinyInteger('discipline_score')->nullable();
            $table->json('tags_json')->nullable();
            $table->string('mistake_type', 64)->nullable();
            $table->text('ai_summary')->nullable();
            $table->timestamps();

            $table->index('demo_operation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
