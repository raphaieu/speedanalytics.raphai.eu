<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('speedway_races', function (Blueprint $table) {
            if (! Schema::hasColumn('speedway_races', 'favorite_position')) {
                $table->unsignedTinyInteger('favorite_position')->nullable()->after('pilot_odds_raw');
            }

            if (! Schema::hasColumn('speedway_races', 'favorite_odd')) {
                $table->decimal('favorite_odd', 8, 2)->nullable()->after('favorite_position');
            }

            if (! Schema::hasColumn('speedway_races', 'second_favorite_position')) {
                $table->unsignedTinyInteger('second_favorite_position')->nullable()->after('favorite_odd');
            }

            if (! Schema::hasColumn('speedway_races', 'second_favorite_odd')) {
                $table->decimal('second_favorite_odd', 8, 2)->nullable()->after('second_favorite_position');
            }

            if (! Schema::hasColumn('speedway_races', 'underdog_position')) {
                $table->unsignedTinyInteger('underdog_position')->nullable()->after('second_favorite_odd');
            }

            if (! Schema::hasColumn('speedway_races', 'underdog_odd')) {
                $table->decimal('underdog_odd', 8, 2)->nullable()->after('underdog_position');
            }

            if (! Schema::hasColumn('speedway_races', 'winner_was_favorite')) {
                $table->boolean('winner_was_favorite')->nullable()->after('tricast_prediction');
            }

            if (! Schema::hasColumn('speedway_races', 'winner_was_underdog')) {
                $table->boolean('winner_was_underdog')->nullable()->after('winner_was_favorite');
            }

            if (! Schema::hasColumn('speedway_races', 'odds_spread')) {
                $table->decimal('odds_spread', 8, 2)->nullable()->after('winner_was_underdog');
            }

            if (! Schema::hasColumn('speedway_races', 'house_margin')) {
                $table->decimal('house_margin', 10, 6)->nullable()->after('odds_spread');
            }

            if (! Schema::hasColumn('speedway_races', 'forecast_hit')) {
                $table->boolean('forecast_hit')->nullable()->after('house_margin');
            }

            if (! Schema::hasColumn('speedway_races', 'tricast_hit')) {
                $table->boolean('tricast_hit')->nullable()->after('forecast_hit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('speedway_races', function (Blueprint $table) {
            $columns = [
                'favorite_position',
                'favorite_odd',
                'second_favorite_position',
                'second_favorite_odd',
                'underdog_position',
                'underdog_odd',
                'winner_was_favorite',
                'winner_was_underdog',
                'odds_spread',
                'house_margin',
                'forecast_hit',
                'tricast_hit',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('speedway_races', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
