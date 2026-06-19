<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('speedway_races', function (Blueprint $table) {
            $table->unsignedTinyInteger('rank_1_position')->nullable()->after('underdog_odd');
            $table->unsignedTinyInteger('rank_2_position')->nullable()->after('rank_1_position');
            $table->unsignedTinyInteger('rank_3_position')->nullable()->after('rank_2_position');
            $table->unsignedTinyInteger('rank_4_position')->nullable()->after('rank_3_position');
            $table->decimal('rank_1_odd', 8, 2)->nullable()->after('rank_4_position');
            $table->decimal('rank_2_odd', 8, 2)->nullable()->after('rank_1_odd');
            $table->decimal('rank_3_odd', 8, 2)->nullable()->after('rank_2_odd');
            $table->decimal('rank_4_odd', 8, 2)->nullable()->after('rank_3_odd');
            $table->string('market_rank_forecast_order', 64)->nullable()->after('rank_4_odd');
            $table->string('market_rank_tricast_order', 128)->nullable()->after('market_rank_forecast_order');
            $table->string('result_forecast_order', 64)->nullable()->after('market_rank_tricast_order');
            $table->decimal('result_forecast_odd', 8, 2)->nullable()->after('result_forecast_order');
            $table->string('result_tricast_order', 128)->nullable()->after('result_forecast_odd');
        });
    }

    public function down(): void
    {
        Schema::table('speedway_races', function (Blueprint $table) {
            $table->dropColumn([
                'rank_1_position',
                'rank_2_position',
                'rank_3_position',
                'rank_4_position',
                'rank_1_odd',
                'rank_2_odd',
                'rank_3_odd',
                'rank_4_odd',
                'market_rank_forecast_order',
                'market_rank_tricast_order',
                'result_forecast_order',
                'result_forecast_odd',
                'result_tricast_order',
            ]);
        });
    }
};
