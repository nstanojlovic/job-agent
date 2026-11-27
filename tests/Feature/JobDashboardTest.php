<?php

use App\Enums\JobStatus;
use App\Models\JobListing;
use App\Models\User;

beforeEach(function () {
    config(['jobagent.admin_emails' => 'me@example.com']);
});

it('shows the job tracker to the admin', function () {
    JobListing::create([
        'source' => 'Manual', 'added_manually' => true, 'status' => JobStatus::New,
        'company' => 'Acme Web', 'title' => 'WordPress Developer', 'notes' => 'Close to the profile.',
    ]);

    $this->actingAs(User::factory()->create(['email' => 'me@example.com']))
        ->get('/admin/job-listings')
        ->assertOk()
        ->assertSee('Acme Web')
        ->assertSee('Close to the profile.');
});

it('keeps everyone else out', function () {
    $this->actingAs(User::factory()->create(['email' => 'someone@example.com']))
        ->get('/admin/job-listings')
        ->assertForbidden();
});

it('never shows companies or statuses on the public landing page', function () {
    JobListing::create([
        'source' => 'Manual', 'added_manually' => true, 'status' => JobStatus::Applied,
        'company' => 'Secret Employer', 'title' => 'WordPress Developer',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('Secret Employer');
});
