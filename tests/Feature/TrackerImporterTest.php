<?php

use App\Enums\JobFit;
use App\Enums\JobStatus;
use App\Models\JobListing;
use App\Services\TrackerImporter;

/** Writes a tracker CSV with the real header and the given rows to a temp file. */
function trackerCsv(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'tracker').'.csv';
    $handle = fopen($path, 'w');

    fputcsv($handle, ['Status', 'Company', 'Role', 'Fit', 'Link', 'Pay', 'Location', 'Type', 'Found', 'Apply by', 'Source', 'Notes'], escape: '');

    foreach ($rows as $row) {
        fputcsv($handle, $row, escape: '');
    }

    fclose($handle);

    return $path;
}

it('imports tracker rows with the same fields as the spreadsheet', function () {
    $path = trackerCsv([
        ['Applied', 'Acme Web d.o.o.', 'WordPress Developer', 'High', 'https://acme.test/jobs/1', '€3,000/mo', 'Serbia, remote', 'B2B contract', '2026-11-21', '2026-12-16', 'Himalayas', 'Close to the profile.'],
        ['Email sent (open?)', 'Beta Labs', 'Senior Laravel Developer', 'Medium', 'Open', 'Not listed', 'Remote, EMEA', 'Not listed', '2026-11-23', '', 'LaraJobs', 'Ask the rate.'],
        ['', '', ' ', '', '', '', '', '', '', '', '', ''],
    ]);

    $counts = app(TrackerImporter::class)->import($path);

    expect($counts)->toBe(['created' => 2, 'updated' => 0, 'skipped' => 1]);

    $acme = JobListing::where('company', 'Acme Web d.o.o.')->sole();
    expect($acme->status)->toBe(JobStatus::Applied)
        ->and($acme->fit)->toBe(JobFit::High)
        ->and($acme->url)->toBe('https://acme.test/jobs/1')
        ->and($acme->salary)->toBe('€3,000/mo')
        ->and($acme->employment_type)->toBe('B2B contract')
        ->and($acme->found_on->toDateString())->toBe('2026-11-21')
        ->and($acme->apply_by->toDateString())->toBe('2026-12-16')
        ->and($acme->source)->toBe('Himalayas')
        ->and($acme->added_manually)->toBeTrue();

    $beta = JobListing::where('company', 'Beta Labs')->sole();
    expect($beta->status)->toBe(JobStatus::EmailSent)
        ->and($beta->url)->toBeNull()
        ->and($beta->salary)->toBeNull()
        ->and($beta->employment_type)->toBeNull()
        ->and($beta->apply_by)->toBeNull();
});

it('updates rows instead of duplicating them on a second import', function () {
    app(TrackerImporter::class)->import(trackerCsv([
        ['New', 'Acme Web', 'WordPress Developer', 'High', '', '', '', '', '2026-11-21', '', 'Himalayas', ''],
    ]));

    $counts = app(TrackerImporter::class)->import(trackerCsv([
        ['Skipped', 'Acme Web GmbH', 'WordPress Developer', 'Low', 'https://acme.test/jobs/1', '', '', '', '2026-11-21', '', 'Himalayas', 'Not for me.'],
    ]));

    expect($counts['updated'])->toBe(1)
        ->and(JobListing::count())->toBe(1)
        ->and(JobListing::sole()->status)->toBe(JobStatus::Skipped)
        ->and(JobListing::sole()->url)->toBe('https://acme.test/jobs/1');
});

it('merges a tracker row into a job the agent already fetched', function () {
    JobListing::create([
        'source' => 'remotive',
        'external_id' => '123',
        'url' => 'https://remotive.test/123',
        'company' => 'Acme Web',
        'title' => 'Sr. WordPress Developer',
    ]);

    app(TrackerImporter::class)->import(trackerCsv([
        ['Applied', 'Acme Web', 'Senior WordPress Developer', 'High', '', '', '', '', '2026-11-21', '', 'Remotive', ''],
    ]));

    expect(JobListing::count())->toBe(1)
        ->and(JobListing::sole()->status)->toBe(JobStatus::Applied)
        ->and(JobListing::sole()->added_manually)->toBeFalse();
});

it('rejects a file that is not the tracker', function () {
    $path = tempnam(sys_get_temp_dir(), 'other');
    file_put_contents($path, "foo,bar\n1,2\n");

    app(TrackerImporter::class)->import($path);
})->throws(RuntimeException::class);
