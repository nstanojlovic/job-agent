<?php

use App\Enums\JobStatus;
use App\Livewire\JobTracker;
use App\Models\JobListing;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    config(['jobagent.admin_emails' => 'me@example.com']);
});

function trackedJob(array $attributes = []): JobListing
{
    return JobListing::create([
        'source' => 'Manual', 'added_manually' => true, 'status' => JobStatus::New,
        'company' => 'Acme Web', 'title' => 'WordPress Developer',
        ...$attributes,
    ]);
}

it('sends guests to the login page', function () {
    $this->get('/tracker')->assertRedirect('/admin/login');
});

it('keeps everyone but the admin out', function () {
    $this->actingAs(User::factory()->create(['email' => 'someone@example.com']))
        ->get('/tracker')
        ->assertForbidden();
});

it('lists jobs for the admin', function () {
    trackedJob(['notes' => 'Close to the profile.']);

    $this->actingAs(User::factory()->create(['email' => 'me@example.com']))
        ->get('/tracker')
        ->assertOk()
        ->assertSee('Acme Web')
        ->assertSee('Close to the profile.');
});

it('filters by tab and changes status inline', function () {
    $new = trackedJob();
    $applied = trackedJob(['company' => 'Beta Labs', 'status' => JobStatus::Applied]);

    $this->actingAs(User::factory()->create(['email' => 'me@example.com']));

    Livewire::test(JobTracker::class)
        ->assertSee('Acme Web')
        ->assertDontSee('Beta Labs')
        ->call('setTab', 'progress')
        ->assertSee('Beta Labs')
        ->assertDontSee('Acme Web')
        ->call('updateStatus', $applied->id, JobStatus::Interview->value);

    expect($applied->fresh()->status)->toBe(JobStatus::Interview)
        ->and($new->fresh()->status)->toBe(JobStatus::New);
});
