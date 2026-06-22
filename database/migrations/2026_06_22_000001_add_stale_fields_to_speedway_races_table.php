<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('speedway_races', function (Blueprint $table) {
            $table->timestamp('stale_at')->nullable()->after('settled_at');
            $table->string('stale_reason', 64)->nullable()->after('stale_at');
        });
    }

    public function down(): void
    {
        Schema::table('speedway_races', function (Blueprint $table) {
            $table->dropColumn(['stale_at', 'stale_reason']);
        });
    }
};
