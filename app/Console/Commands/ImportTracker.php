<?php

namespace App\Console\Commands;

use App\Services\TrackerImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportTracker extends Command
{
    protected $signature = 'jobs:import-tracker {path : Path to the tracker CSV (exported from Google Sheets)}';

    protected $description = 'Import or update jobs from the spreadsheet job tracker';

    public function handle(TrackerImporter $importer): int
    {
        try {
            $counts = $importer->import($this->argument('path'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Created {$counts['created']}, updated {$counts['updated']}, skipped {$counts['skipped']} empty rows.");

        return self::SUCCESS;
    }
}
