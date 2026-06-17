<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('speedway_races', function (Blueprint $table) {
            if (! Schema::hasColumn('speedway_races', 'tricast_winner_hit')) {
                $table->boolean('tricast_winner_hit')->nullable()->after('tricast_hit');
            }

            if (! Schema::hasColumn('speedway_races', 'tricast_exact_hit')) {
                $table->boolean('tricast_exact_hit')->nullable()->after('tricast_winner_hit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('speedway_races', function (Blueprint $table) {
            if (Schema::hasColumn('speedway_races', 'tricast_exact_hit')) {
                $table->dropColumn('tricast_exact_hit');
            }

            if (Schema::hasColumn('speedway_races', 'tricast_winner_hit')) {
                $table->dropColumn('tricast_winner_hit');
            }
        });
    }
};
