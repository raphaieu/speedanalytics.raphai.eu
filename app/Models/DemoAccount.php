<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DemoAccount extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'initial_balance',
        'current_balance',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'initial_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'is_default' => 'boolean',
        ];
    }

    public function operations(): HasMany
    {
        return $this->hasMany(DemoOperation::class);
    }

    public function bankrollTransactions(): HasMany
    {
        return $this->hasMany(BankrollTransaction::class);
    }
}
