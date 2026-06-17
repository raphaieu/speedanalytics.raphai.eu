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
        'winner_position',
        'winner_color',
        'winner_odd',
        'pilot_name',
        'prediction',
        'prediction_odd',
        'tricast_prediction',
        'first_seen_at',
        'settled_at',
        'raw_pending_payload',
        'raw_result_payload',
        'last_payload_id',
    ];

    protected function casts(): array
    {
        return [
            'winner_odd' => 'decimal:2',
            'prediction_odd' => 'decimal:2',
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
