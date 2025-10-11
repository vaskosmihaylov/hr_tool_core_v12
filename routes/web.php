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

// Replacement Worker Route - Add worker to specific month for object
Route::get('/service/presence/addWorker/{object_id}/{selected_month}', function ($object_id, $selected_month) {
    if (!$object_id || !$selected_month) {
        return redirect('/service/presences')->with('error', 'Невалидни параметри за добавяне на работник');
    }

    $dateParts = explode('-', $selected_month);
    if (count($dateParts) !== 2) {
        return redirect('/service/presences')->with('error', 'Невалиден формат на месеца');
    }

    $month = (int) $dateParts[0];
    $year = (int) $dateParts[1];

    if ($month < 1 || $month > 12 || $year < 2020 || $year > 2030) {
        return redirect('/service/presences')->with('error', 'Невалиден месец или година');
    }

    return redirect("/service/presences/monthly/{$object_id}/{$selected_month}/workers/add");
})->middleware(['web', 'auth'])->name('service.presence.add-worker');

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/service/presence/config/{workplaceId}/{date}', function (int $workplaceId, string $date) {
        return redirect("/service/presences/config/{$workplaceId}/{$date}");
    })->name('service.presence.config.redirect');

    Route::get('/service/presence/activity/add/{workplaceId}/{date}', function (int $workplaceId, string $date) {
        return redirect("/service/presences/config/{$workplaceId}/{$date}/activity/add");
    })->name('service.presence.activity.add.redirect');

    Route::get('/service/presence/activity/edit/{activityId}/{date}', function (int $activityId, string $date) {
        $activity = \viki\Service\Models\Elequent\WorkPlaceActivity::find($activityId);

        if (!$activity) {
            return redirect('/service/presences')->with('error', 'Дейността не беше намерена.');
        }

        return redirect("/service/presences/config/{$activity->work_place_id}/{$date}/activity/{$activityId}");
    })->name('service.presence.activity.edit.redirect');

    Route::get('/service/archives/{archive}/export', [App\Http\Controllers\ArchiveExportController::class, '__invoke'])
        ->name('service.archives.export');
});
