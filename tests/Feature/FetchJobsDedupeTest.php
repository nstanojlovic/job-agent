<?php

use App\Enums\JobStatus;
use App\Models\JobListing;
use Illuminate\Support\Facades\Http;

/** Fakes every source: Remotive returns the given jobs, the others return nothing. */
function fakeSources(array $remotiveJobs): void
{
    $emptyRss = '<?xml version="1.0"?><rss><channel></channel></rss>';

    Http::fake([
        'remotive.com/*' => Http::response(['jobs' => $remotiveJobs]),
        'remoteok.com/*' => Http::response([['legal' => 'notice']]),
        'weworkremotely.com/*' => Http::response($emptyRss),
        'jobs.wordpress.net/*' => Http::response($emptyRss),
    ]);
}

function remotiveJob(int $id, string $company, string $title): array
{
    return [
        'id' => $id,
        'url' => "https://remotive.test/{$id}",
        'title' => $title,
        'company_name' => $company,
        'candidate_required_location' => 'Europe',
        'tags' => ['wordpress'],
        'description' => 'WordPress role',
        'publication_date' => now()->subDay()->toIso8601String(),
    ];
}

it('skips jobs already tracked, from blocked agencies, or from companies I skipped', function () {
    JobListing::create([
        'source' => 'Himalayas', 'external_id' => 'tracker:1', 'added_manually' => true,
        'company' => 'Acme Web', 'title' => 'WordPress Developer', 'status' => JobStatus::Applied,
    ]);
    JobListing::create([
        'source' => 'Joberty', 'external_id' => 'tracker:2', 'added_manually' => true,
        'company' => 'Casino Corp', 'title' => 'Full Stack WordPress Engineer', 'status' => JobStatus::Rejected,
    ]);

    fakeSources([
        remotiveJob(1, 'Acme Web GmbH', 'WordPress Developer'),       // same company + role
        remotiveJob(2, 'Proxify', 'Senior WordPress Developer'),       // known agency
        remotiveJob(3, 'Casino Corp', 'Senior PHP Developer'),         // company I was rejected by
        remotiveJob(4, 'Fresh Studio', 'WordPress Developer'),         // genuinely new
    ]);

    $this->artisan('jobs:fetch', ['--no-ai' => true])->assertSuccessful();

    expect(JobListing::count())->toBe(3)
        ->and(JobListing::where('company', 'Fresh Studio')->sole()->found_on->isToday())->toBeTrue()
        ->and(JobListing::where('company', 'Proxify')->exists())->toBeFalse();
});

it('never sends manual or imported jobs to the screener', function () {
    JobListing::create([
        'source' => 'Manual', 'added_manually' => true,
        'company' => 'Acme Web', 'title' => 'WordPress Developer',
    ]);

    expect(JobListing::unscored()->count())->toBe(0);
});
