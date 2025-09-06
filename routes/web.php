<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Login route for middleware redirects - now redirects to working Filament login
Route::get('/login', function () {
    return redirect('/admin/login');
})->name('login');

// Excel export routes
Route::get('/service/reports/export-excel', [App\Http\Controllers\ExcelExportController::class, 'exportReport'])
    ->middleware(['web', 'auth'])
    ->name('service.reports.export-excel');

Route::get('/service/presence/export-table', [App\Http\Controllers\PresenceExportController::class, 'exportPresenceTable'])
    ->middleware(['web', 'auth'])
    ->name('service.presence.export-table');

Route::get('/service/presence/export-monthly', [App\Http\Controllers\PresenceExportController::class, 'exportMonthlyPresence'])
    ->middleware(['web', 'auth'])
    ->name('service.presence.export-monthly');

// Test route for permission system
Route::get('/test-permissions', function () {
    $supervisor = App\Models\User::where('email', 'supervisor@example.com')->first();
    $hr = App\Models\User::where('email', 'hr@example.com')->first();
    
    if (!$supervisor || !$hr) {
        return 'Test users not found';
    }
    
    $testUrls = [
        'service/region',
        'service/client', 
        'service/worker/create',
        'service/archive',
        'service/presence',
        'service/approvement'
    ];
    
    $results = [];
    
    foreach ($testUrls as $url) {
        $results[$url] = [
            'supervisor' => $supervisor->hasPermissionUrl($url),
            'hr' => $hr->hasPermissionUrl($url)
        ];
    }
    
    return response()->json($results, JSON_PRETTY_PRINT);
})->middleware('web');

// Simple presence configuration route (no Livewire)
Route::get('/service/presence-configure', function (Illuminate\Http\Request $request) {
    $workplaceId = $request->get('workplace_id');
    $date = $request->get('date');
    
    if (!$workplaceId || !$date) {
        return redirect('/service/presences')->with('error', 'Моля изберете обект и месец');
    }
    
    // Redirect to monthly management
    return redirect("/service/presences/monthly/{$workplaceId}/{$date}");
})->middleware(['web', 'auth']);
