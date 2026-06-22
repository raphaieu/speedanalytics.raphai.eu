<?php

namespace App\Console\Commands;

use App\Services\Speedway\PendingRaceReconciliationService;
use Illuminate\Console\Command;

class ReconcilePendingRacesCommand extends Command
{
    protected $signature = 'speedway:reconcile-pending-races';

    protected $description = 'Reconcilia corridas pending antigas: tenta settled via payload bruto ou marca gap/stale';

    public function handle(PendingRaceReconciliationService $reconciliationService): int
    {
        $stats = $reconciliationService->reconcile();

        $this->info(sprintf(
            'Reconciliação concluída: %d verificadas, %d settled, %d marcadas stale.',
            $stats['checked'],
            $stats['settled'],
            $stats['marked_stale'],
        ));

        return self::SUCCESS;
    }
}
