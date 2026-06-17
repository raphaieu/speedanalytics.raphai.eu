<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpeedwayRace extends Model
{
    protected $fillable = [
        'external_id',
        'status',
        'race_hour',
        'race_minute',
        'pilot_odds_raw',
        'favorite_position',
        'favorite_odd',
        'second_favorite_position',
        'second_favorite_odd',
        'underdog_position',
        'underdog_odd',
        'winner_position',
        'winner_color',
        'winner_odd',
        'pilot_name',
        'prediction',
        'prediction_odd',
        'tricast_prediction',
        'winner_was_favorite',
        'winner_was_underdog',
        'winner_odd_rank',
        'odds_spread',
        'house_margin',
        'forecast_hit',
        'tricast_hit',
        'tricast_winner_hit',
        'tricast_exact_hit',
        'first_seen_at',
        'settled_at',
        'raw_pending_payload',
        'raw_result_payload',
        'last_payload_id',
    ];

    protected function casts(): array
    {
        return [
            'favorite_odd' => 'decimal:2',
            'second_favorite_odd' => 'decimal:2',
            'underdog_odd' => 'decimal:2',
            'winner_odd' => 'decimal:2',
            'prediction_odd' => 'decimal:2',
            'winner_was_favorite' => 'boolean',
            'winner_was_underdog' => 'boolean',
            'odds_spread' => 'decimal:2',
            'house_margin' => 'decimal:6',
            'forecast_hit' => 'boolean',
            'tricast_hit' => 'boolean',
            'tricast_winner_hit' => 'boolean',
            'tricast_exact_hit' => 'boolean',
            'first_seen_at' => 'datetime',
            'settled_at' => 'datetime',
            'raw_pending_payload' => 'array',
            'raw_result_payload' => 'array',
        ];
    }

    public function lastPayload(): BelongsTo
    {
        return $this->belongsTo(SpeedwayPayload::class, 'last_payload_id');
    }
}
