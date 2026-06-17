<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpeedwayPayload extends Model
{
    protected $fillable = [
        'source',
        'mode',
        'source_url',
        'captured_at',
        'data_atualizacao',
        'payload',
        'summary',
        'processing_status',
        'processed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'processed_at' => 'datetime',
            'payload' => 'array',
            'summary' => 'array',
        ];
    }

    public function races(): HasMany
    {
        return $this->hasMany(SpeedwayRace::class, 'last_payload_id');
    }
}
