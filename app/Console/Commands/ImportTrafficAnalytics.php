<?php

namespace App\Console\Commands;

use App\Services\TrafficAnalytics\AnalyticsIngestionService;
use App\Services\TrafficAnalytics\EntityResolverService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportTrafficAnalytics extends Command
{
    protected $signature = 'analytics:import {path} {--source=ntopng}';

    protected $description = 'Import summarized traffic analytics JSON from an external analyzer.';

    public function handle(AnalyticsIngestionService $ingestion, EntityResolverService $resolver): int
    {
        $path = (string) $this->argument('path');
        $sourceName = (string) $this->option('source');

        if (! File::exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $payload = json_decode((string) File::get($path), true);

        if (! is_array($payload)) {
            $this->error('The provided file does not contain valid JSON.');

            return self::FAILURE;
        }

        $source = $ingestion->source($sourceName);
        $normalized = $source->normalize($payload);
        $result = $ingestion->ingest($normalized, $source, $resolver);

        $this->info("Imported {$result['imported']} observations from {$sourceName}.");
        $this->line("Unknown bucket assignments: {$result['unknown']}");

        return self::SUCCESS;
    }
}
