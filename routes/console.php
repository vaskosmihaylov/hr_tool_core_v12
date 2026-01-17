<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule holidays import to run yearly on January 1st at 2:00 AM
Schedule::command('holidays:import')
    ->yearlyOn(1, 1, '02:00')
    ->timezone('Europe/Sofia')
    ->onSuccess(function () {
        info('Holidays imported successfully for ' . date('Y'));
    })
    ->onFailure(function () {
        error_log('Failed to import holidays for ' . date('Y'));
    });
