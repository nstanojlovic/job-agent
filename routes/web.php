<?php

use App\Support\FunnelStats;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('landing', ['stats' => FunnelStats::get()]));
