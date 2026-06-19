<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demo_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('speedway_race_id')->nullable()->constrained()->nullOnDelete();
            $table->string('origin', 32)->index();
            $table->string('market_type', 32)->index();
            $table->string('bet_type', 32);
            $table->string('status', 32)->index();
            $table->string('result', 32)->nullable();
            $table->boolean('risk_enforced')->default(true);
            $table->boolean('after_stop')->default(false);
            $table->string('rule_compliance', 32)->default('not_applicable');
            $table->string('mistake_type', 64)->nullable();
            $table->json('tags')->nullable();
            $table->json('entry_payload_json');
            $table->json('context_snapshot_json')->nullable();
            $table->decimal('stake_amount', 12, 2);
            $table->decimal('potential_gross_return', 12, 2);
            $table->decimal('potential_net_profit', 12, 2);
            $table->decimal('actual_gross_return', 12, 2)->nullable();
            $table->decimal('actual_net_profit', 12, 2)->nullable();
            $table->decimal('profit_loss', 12, 2)->nullable();
            $table->decimal('bankroll_before', 12, 2);
            $table->decimal('bankroll_after', 12, 2)->nullable();
            $table->unsignedTinyInteger('entry_position')->nullable();
            $table->string('entry_color', 32)->nullable();
            $table->decimal('entry_odd', 8, 2)->nullable();
            $table->text('reason_snapshot')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('settled_at')->nullable()->index();
            $table->timestamps();

            $table->index(['demo_account_id', 'status']);
            $table->index(['demo_account_id', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_operations');
    }
};
