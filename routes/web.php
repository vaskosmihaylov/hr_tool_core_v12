<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Login route for middleware redirects - now redirects to working Filament login
Route::get('/login', function () {
    return redirect('/admin/login');
})->name('login');

// Excel export route
Route::get('/service/reports/export-excel', [App\Http\Controllers\ExcelExportController::class, 'exportReport'])
    ->middleware(['web', 'auth'])
    ->name('service.reports.export-excel');
