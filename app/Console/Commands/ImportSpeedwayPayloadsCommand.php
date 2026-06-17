<?php

namespace App\Console\Commands;

use App\Jobs\ProcessSpeedwayPayloadJob;
use App\Models\SpeedwayPayload;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class ImportSpeedwayPayloadsCommand extends Command
{
    protected $signature = 'speedway:import-payloads
                            {--path= : Diretório com JSONs (default: collector/storage/payloads)}
                            {--sync : Processa inline em vez de enfileirar}';

    protected $description = 'Importa payloads JSON do collector para o MySQL';

    public function handle(): int
    {
        $path = $this->option('path') ?: base_path('collector/storage/payloads');

        if (! File::isDirectory($path)) {
            $this->error("Diretório não encontrado: {$path}");

            return self::FAILURE;
        }

        $files = collect(File::files($path))
            ->filter(fn ($file) => $file->getExtension() === 'json')
            ->sortBy(fn ($file) => $file->getFilename())
            ->values();

        if ($files->isEmpty()) {
            $this->warn('Nenhum arquivo JSON encontrado.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($files->count());
        $bar->start();

        $imported = 0;

        foreach ($files as $file) {
            $raw = json_decode(File::get($file->getPathname()), true);

            if (! is_array($raw) || ! isset($raw['payload'])) {
                $bar->advance();
                continue;
            }

            $payloadRecord = SpeedwayPayload::query()->create([
                'source' => $raw['source'] ?? 'bbtips',
                'mode' => $raw['mode'] ?? null,
                'source_url' => $raw['source_url'] ?? null,
                'captured_at' => isset($raw['captured_at']) ? Carbon::parse($raw['captured_at']) : now(),
                'data_atualizacao' => $raw['data_atualizacao'] ?? null,
                'payload' => $raw['payload'],
                'summary' => $raw['summary'] ?? null,
                'processing_status' => 'pending',
            ]);

            if ($this->option('sync')) {
                ProcessSpeedwayPayloadJob::dispatchSync($payloadRecord->id);
            } else {
                ProcessSpeedwayPayloadJob::dispatch($payloadRecord->id);
            }

            $imported++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Importados {$imported} payloads de {$files->count()} arquivos.");

        return self::SUCCESS;
    }
}
