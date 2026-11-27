<?php

use App\Models\JobListing;

it('normalises company names', function (string $company, string $expected) {
    expect(JobListing::companyKey($company))->toBe($expected);
})->with([
    ['SDF Systems GmbH', 'sdf systems'],
    ['Speedline Hub (iGaming client)', 'speedline hub'],
    ['MCM 965 d.o.o.', 'mcm 965'],
    ['REIZ TECH UAB', 'reiz tech'],
    ['Lemon.io', 'lemon io'],
]);

it('builds the same key for the same role written differently', function () {
    expect(JobListing::dedupeKey('Acme Web GmbH', 'Sr. WordPress Developer (Remote)'))
        ->toBe(JobListing::dedupeKey('acme web', 'Senior WordPress Developer'));
});

it('has no key when the company is unknown', function () {
    expect(JobListing::dedupeKey(null, 'WordPress Developer'))->toBeNull()
        ->and(JobListing::dedupeKey('  ', 'WordPress Developer'))->toBeNull();
});
