<?php

namespace App\Models;

use App\Enums\Demo\DemoBetType;
use App\Enums\Demo\DemoMarketType;
use App\Enums\Demo\DemoOperationOrigin;
use App\Enums\Demo\DemoOperationResult;
use App\Enums\Demo\DemoOperationStatus;
use App\Enums\Demo\RuleCompliance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DemoOperation extends Model
{
    protected $fillable = [
        'demo_account_id',
        'user_id',
        'speedway_race_id',
        'origin',
        'market_type',
        'bet_type',
        'status',
        'result',
        'risk_enforced',
        'after_stop',
        'rule_compliance',
        'mistake_type',
        'tags',
        'entry_payload_json',
        'context_snapshot_json',
        'stake_amount',
        'potential_gross_return',
        'potential_net_profit',
        'actual_gross_return',
        'actual_net_profit',
        'profit_loss',
        'bankroll_before',
        'bankroll_after',
        'entry_position',
        'entry_color',
        'entry_odd',
        'reason_snapshot',
        'opened_at',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'origin' => DemoOperationOrigin::class,
            'market_type' => DemoMarketType::class,
            'bet_type' => DemoBetType::class,
            'status' => DemoOperationStatus::class,
            'result' => DemoOperationResult::class,
            'rule_compliance' => RuleCompliance::class,
            'risk_enforced' => 'boolean',
            'after_stop' => 'boolean',
            'tags' => 'array',
            'entry_payload_json' => 'array',
            'context_snapshot_json' => 'array',
            'stake_amount' => 'decimal:2',
            'potential_gross_return' => 'decimal:2',
            'potential_net_profit' => 'decimal:2',
            'actual_gross_return' => 'decimal:2',
            'actual_net_profit' => 'decimal:2',
            'profit_loss' => 'decimal:2',
            'bankroll_before' => 'decimal:2',
            'bankroll_after' => 'decimal:2',
            'entry_odd' => 'decimal:2',
            'opened_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    public function demoAccount(): BelongsTo
    {
        return $this->belongsTo(DemoAccount::class);
    }

    public function speedwayRace(): BelongsTo
    {
        return $this->belongsTo(SpeedwayRace::class);
    }

    public function bankrollTransactions(): HasMany
    {
        return $this->hasMany(BankrollTransaction::class);
    }

    public function journalEntry(): HasOne
    {
        return $this->hasOne(JournalEntry::class);
    }
}
