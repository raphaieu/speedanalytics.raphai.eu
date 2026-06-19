<?php

namespace App\Support;

use App\Enums\Demo\DemoOperationStatus;
use App\Models\DemoAccount;
use App\Models\DemoOperation;
use App\Models\JournalEntry;

class DemoPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function account(DemoAccount $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'slug' => $account->slug,
            'initial_balance' => $account->initial_balance,
            'current_balance' => $account->current_balance,
            'is_default' => $account->is_default,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function operation(DemoOperation $operation): array
    {
        return [
            'id' => $operation->id,
            'demo_account_id' => $operation->demo_account_id,
            'speedway_race_id' => $operation->speedway_race_id,
            'race' => $operation->speedwayRace ? [
                'id' => $operation->speedwayRace->id,
                'external_id' => $operation->speedwayRace->external_id,
                'status' => $operation->speedwayRace->status,
                'race_hour' => $operation->speedwayRace->race_hour,
                'race_minute' => $operation->speedwayRace->race_minute,
            ] : null,
            'origin' => $operation->origin->value,
            'market_type' => $operation->market_type->value,
            'bet_type' => $operation->bet_type->value,
            'status' => $operation->status->value,
            'result' => $operation->result?->value,
            'risk_enforced' => $operation->risk_enforced,
            'after_stop' => $operation->after_stop,
            'rule_compliance' => $operation->rule_compliance->value,
            'mistake_type' => $operation->mistake_type,
            'tags' => $operation->tags ?? [],
            'entry_payload_json' => $operation->entry_payload_json,
            'stake_amount' => $operation->stake_amount,
            'potential_gross_return' => $operation->potential_gross_return,
            'potential_net_profit' => $operation->potential_net_profit,
            'actual_gross_return' => $operation->actual_gross_return,
            'actual_net_profit' => $operation->actual_net_profit,
            'profit_loss' => $operation->profit_loss,
            'bankroll_before' => $operation->bankroll_before,
            'bankroll_after' => $operation->bankroll_after,
            'entry_position' => $operation->entry_position,
            'entry_color' => $operation->entry_color,
            'entry_odd' => $operation->entry_odd,
            'opened_at' => $operation->opened_at?->toIso8601String(),
            'settled_at' => $operation->settled_at?->toIso8601String(),
            'settlement_mode' => self::settlementMode($operation),
            'journal' => $operation->journalEntry
                ? self::journal($operation->journalEntry)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function bankrollCurve(array $curve): array
    {
        return $curve;
    }

    private static function settlementMode(DemoOperation $operation): ?string
    {
        if ($operation->status !== DemoOperationStatus::Settled) {
            return null;
        }

        $settlement = $operation->context_snapshot_json['settlement'] ?? null;

        if (! is_array($settlement)) {
            return null;
        }

        $mode = $settlement['mode'] ?? null;

        return is_string($mode) ? $mode : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function journal(JournalEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'note' => $entry->note,
            'emotion' => $entry->emotion,
            'confidence_level' => $entry->confidence_level,
            'discipline_score' => $entry->discipline_score,
            'tags' => $entry->tags_json ?? [],
            'mistake_type' => $entry->mistake_type,
            'created_at' => $entry->created_at?->toIso8601String(),
        ];
    }
}
