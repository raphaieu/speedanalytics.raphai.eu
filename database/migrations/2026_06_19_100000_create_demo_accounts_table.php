<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 128);
            $table->string('slug', 64)->unique();
            $table->decimal('initial_balance', 12, 2);
            $table->decimal('current_balance', 12, 2);
            $table->boolean('is_default')->default(false)->index();
            $table->timestamps();
        });

        $now = now();

        DB::table('demo_accounts')->insert([
            'name' => 'Conta manual padrão',
            'slug' => 'manual-default',
            'initial_balance' => 100,
            'current_balance' => 100,
            'is_default' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_accounts');
    }
};
