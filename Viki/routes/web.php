<?php

// Viki Service Routes
// This is a placeholder until we copy the full routes from the old app

Route::group(['middleware' => ['web', 'auth']], function () {
    Route::get('service/test', function () {
        return 'Viki Service Package Loaded Successfully!';
    })->name('service.test');
});
