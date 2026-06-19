<?php

namespace App\Console\Commands;

use App\Models\SpeedwayRace;
use App\Services\Speedway\RaceMetricsService;
use Illuminate\Console\Command;

class BackfillSpeedwayRaceRanksCommand extends Command
{
    protected $signature = 'speedway:backfill-race-ranks
                            {--chunk=500 : Quantidade de corridas por chunk}';

    protected $description = 'Preenche ranking por odds, ordens teóricas de mercado e resultados forecast/tricast em corridas existentes';

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

        $this->info("Ranking e ordens recalculados para {$processed} corridas.");

        return self::SUCCESS;
    }
}
