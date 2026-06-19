<?php

namespace App\Exceptions\Demo;

use RuntimeException;

class InsufficientDemoBalanceException extends RuntimeException
{
    public static function forStake(float $available, float $required): self
    {
        return new self("Saldo demo insuficiente: disponível {$available}, necessário {$required}.");
    }
}
