<?php

namespace App\Console\Commands;

use App\Models\SpeedwayRace;
use App\Services\Speedway\RaceMetricsService;
use Illuminate\Console\Command;

class RecalculateSpeedwayMetricsCommand extends Command
{
    protected $signature = 'speedway:recalculate-metrics
                            {--chunk=500 : Quantidade de corridas por chunk}';

    protected $description = 'Recalcula métricas base das corridas Speedway já salvas';

    public function handle(RaceMetricsService $metricsService): int
    {
        $chunkSize = max((int) $this->option('chunk'), 1);
        $processed = 0;

        SpeedwayRace::query()
            ->chunkById($chunkSize, function (iterable $races) use (&$processed, $metricsService): void {
                foreach ($races as $race) {
                    if (! $race instanceof SpeedwayRace) {
                        continue;
                    }

                    $race->update($metricsService->calculate($race));
                    $processed++;
                }
            });

        $this->info("Métricas recalculadas para {$processed} corridas.");

        return self::SUCCESS;
    }
}
