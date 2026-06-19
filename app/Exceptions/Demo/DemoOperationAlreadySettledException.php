<?php

namespace App\Exceptions\Demo;

use RuntimeException;

class DemoOperationAlreadySettledException extends RuntimeException
{
    public static function forOperation(int $operationId): self
    {
        return new self("Operação demo #{$operationId} já foi liquidada.");
    }
}
