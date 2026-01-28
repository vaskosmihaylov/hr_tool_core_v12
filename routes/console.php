<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule holidays import to run daily during the first 7 days of January at 2:00 AM
// This ensures holidays are imported even if the system is set up after January 1st
// and provides retry attempts in case of failures
Schedule::command('holidays:import')
    ->dailyAt('02:00')
    ->timezone('Europe/Sofia')
    ->when(function () {
        // Only run during January 1-7 to import holidays for the current year
        return date('m') === '01' && date('d') <= '07';
    })
    ->onSuccess(function () {
        info('Holidays imported successfully for ' . date('Y'));
    })
    ->onFailure(function () {
        error_log('Failed to import holidays for ' . date('Y'));
    });
