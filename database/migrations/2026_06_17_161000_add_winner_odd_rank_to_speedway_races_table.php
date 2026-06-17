<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('speedway_races', function (Blueprint $table) {
            if (! Schema::hasColumn('speedway_races', 'winner_odd_rank')) {
                $table->unsignedTinyInteger('winner_odd_rank')->nullable()->after('winner_was_underdog');
            }
        });
    }

    public function down(): void
    {
        Schema::table('speedway_races', function (Blueprint $table) {
            if (Schema::hasColumn('speedway_races', 'winner_odd_rank')) {
                $table->dropColumn('winner_odd_rank');
            }
        });
    }
};
