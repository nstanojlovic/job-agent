<?php

namespace App\Services;

use App\Enums\JobFit;
use App\Enums\JobStatus;
use App\Models\JobListing;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Imports the spreadsheet job tracker (CSV export) into job_listings.
 *
 * Expected header: Status, Company, Role, Fit, Link, Pay, Location, Type,
 * Found, Apply by, Source, Notes. Rows are matched on company + role, so
 * importing the same file twice updates rows instead of duplicating them.
 */
class TrackerImporter
{
    private const REQUIRED_COLUMNS = ['status', 'company', 'role'];

    /** @return array{created: int, updated: int, skipped: int} */
    public function import(string $path): array
    {
        $handle = @fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Could not open {$path}");
        }

        try {
            $header = fgetcsv($handle, escape: '');

            if ($header === false) {
                throw new RuntimeException('The CSV file is empty.');
            }

            $columns = array_map(fn ($name) => Str::of((string) $name)->replace("\u{FEFF}", '')->lower()->trim()->toString(), $header);

            if (array_diff(self::REQUIRED_COLUMNS, $columns) !== []) {
                throw new RuntimeException('This doesn’t look like the tracker CSV. It needs at least Status, Company and Role columns.');
            }

            $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0];

            while (($cells = fgetcsv($handle, escape: '')) !== false) {
                $row = $this->combine($columns, $cells);

                if (blank($row['company'] ?? null) || blank($row['role'] ?? null)) {
                    $counts['skipped']++;

                    continue;
                }

                $counts[$this->importRow($row) ? 'created' : 'updated']++;
            }

            return $counts;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array<string, string>  $row
     * @return bool True when a new listing was created.
     */
    private function importRow(array $row): bool
    {
        $company = trim($row['company']);
        $title = trim($row['role']);
        $key = JobListing::dedupeKey($company, $title);

        $listing = ($key !== null ? JobListing::query()->where('dedupe_key', $key)->first() : null)
            ?? new JobListing([
                'added_manually' => true,
                'source' => $this->value($row, 'source') ?? 'Tracker',
                'external_id' => mb_substr('tracker:'.($key ?? Str::uuid()), 0, 255),
            ]);

        $listing->fill(array_filter([
            'status' => JobStatus::fromTracker($row['status'] ?? ''),
            'company' => mb_substr($company, 0, 255),
            'title' => mb_substr($title, 0, 255),
            'fit' => JobFit::tryFrom(ucfirst(strtolower(trim($row['fit'] ?? '')))),
            'url' => $this->url($row['link'] ?? null),
            'salary' => $this->limited($this->value($row, 'pay')),
            'location' => $this->limited($this->value($row, 'location')),
            'employment_type' => $this->limited($this->value($row, 'type')),
            'found_on' => $this->date($row['found'] ?? null),
            'apply_by' => $this->date($row['apply by'] ?? null),
            'notes' => $this->value($row, 'notes'),
        ], fn ($value) => $value !== null));

        // A blank "Apply by" in the tracker means there is no deadline.
        if (blank($row['apply by'] ?? null)) {
            $listing->apply_by = null;
        }

        $created = ! $listing->exists;
        $listing->save();

        return $created;
    }

    /**
     * @param  list<string>  $columns
     * @param  list<string|null>  $cells
     * @return array<string, string>
     */
    private function combine(array $columns, array $cells): array
    {
        $row = [];

        foreach ($columns as $i => $column) {
            $row[$column] = trim((string) ($cells[$i] ?? ''));
        }

        return $row;
    }

    /** Cell value, with the tracker's "Not listed" treated as empty. */
    private function value(array $row, string $column): ?string
    {
        $value = trim($row[$column] ?? '');

        return $value === '' || strcasecmp($value, 'Not listed') === 0 ? null : $value;
    }

    private function limited(?string $value): ?string
    {
        return $value === null ? null : mb_substr($value, 0, 255);
    }

    /** Google Sheets exports a hyperlinked cell as its text ("Open"), so only real URLs are kept. */
    private function url(?string $value): ?string
    {
        $value = trim((string) $value);

        return filter_var($value, FILTER_VALIDATE_URL) && Str::startsWith($value, ['http://', 'https://'])
            ? mb_substr($value, 0, 1024)
            : null;
    }

    private function date(?string $value): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse(trim($value))->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
