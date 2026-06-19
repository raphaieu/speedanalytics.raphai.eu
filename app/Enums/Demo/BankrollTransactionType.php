<?php

namespace App\Enums\Demo;

enum BankrollTransactionType: string
{
    case ManualAdjustment = 'manual_adjustment';
    case OperationStake = 'operation_stake';
    case OperationSettlement = 'operation_settlement';
}
