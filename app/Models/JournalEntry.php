<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntry extends Model
{
    protected $fillable = [
        'user_id',
        'demo_operation_id',
        'note',
        'emotion',
        'confidence_level',
        'discipline_score',
        'tags_json',
        'mistake_type',
        'ai_summary',
    ];

    protected function casts(): array
    {
        return [
            'tags_json' => 'array',
        ];
    }

    public function demoOperation(): BelongsTo
    {
        return $this->belongsTo(DemoOperation::class);
    }
}
