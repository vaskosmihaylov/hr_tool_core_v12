<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Login route for middleware redirects - now redirects to working Filament login
Route::get('/login', function () {
    return redirect('/admin/login');
})->name('login');

// PDF Export route for VikiReports
Route::get('/service/reports/export-pdf', function () {
    // Get the session data
    $reportData = session('pdf_export_data');
    $filters = session('pdf_export_filters');
    
    if (empty($reportData) || empty($filters)) {
        abort(404, 'No report data found. Please generate a report first.');
    }
    
    try {
        $pdf = \Spatie\LaravelPdf\Facades\Pdf::view('service::report.export', [
            'workerRecords' => $reportData['workerRecords'],
            'arraySum' => $reportData['arraySum'],
            'month_id' => $filters['month_id'],
            'year_id' => $filters['year_id']
        ]);

        $filename = 'viki_справка_за_месец_' . $filters['month_id'] . '-' . $filters['year_id'] . '.pdf';
        
        // Log activity
        activity()
            ->performedOn(\Illuminate\Support\Facades\Auth::user())
            ->causedBy(\Illuminate\Support\Facades\Auth::user())
            ->log('PDF експорт завършен за ' . $filename);
        
        return $pdf->name($filename)->download();
        
    } catch (\Exception $e) {
        abort(500, 'PDF generation failed: ' . $e->getMessage());
    }
})->middleware(['web', 'auth'])->name('service.reports.export-pdf');
