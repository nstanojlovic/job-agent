<?php

use App\Livewire\JobTracker;
use App\Support\FunnelStats;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('landing', ['stats' => FunnelStats::get()]));

// Private job tracker: only the admin emails from JOBAGENT_ADMIN_EMAILS.
// Guests are sent to the dashboard login and come back here afterwards.
Route::get('/tracker', function () {
    if (! auth()->check()) {
        return redirect()->guest(Filament::getPanel('admin')->getLoginUrl());
    }

    abort_unless(JobTracker::canView(), 403);

    return view('tracker');
})->middleware('web')->name('tracker');
