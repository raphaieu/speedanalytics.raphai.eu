<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('speedway_races', function (Blueprint $table) {
            $table->id();
            $table->string('external_id', 64)->unique();
            $table->string('status', 16)->index();
            $table->string('race_hour', 8)->nullable();
            $table->string('race_minute', 8)->nullable();
            $table->string('pilot_odds_raw', 128)->nullable();
            $table->unsignedTinyInteger('winner_position')->nullable();
            $table->string('winner_color', 32)->nullable();
            $table->decimal('winner_odd', 8, 2)->nullable();
            $table->string('pilot_name', 128)->nullable();
            $table->string('prediction', 64)->nullable();
            $table->decimal('prediction_odd', 8, 2)->nullable();
            $table->string('tricast_prediction', 128)->nullable();
            $table->timestamp('first_seen_at')->nullable()->index();
            $table->timestamp('settled_at')->nullable()->index();
            $table->json('raw_pending_payload')->nullable();
            $table->json('raw_result_payload')->nullable();
            $table->foreignId('last_payload_id')->nullable()->constrained('speedway_payloads')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('speedway_races');
    }
};
