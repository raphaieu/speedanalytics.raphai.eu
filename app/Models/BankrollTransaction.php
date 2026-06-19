<?php

namespace App\Models;

use App\Enums\Demo\BankrollTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankrollTransaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'demo_account_id',
        'demo_operation_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'metadata_json',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => BankrollTransactionType::class,
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'metadata_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function demoAccount(): BelongsTo
    {
        return $this->belongsTo(DemoAccount::class);
    }

    public function demoOperation(): BelongsTo
    {
        return $this->belongsTo(DemoOperation::class);
    }
}
