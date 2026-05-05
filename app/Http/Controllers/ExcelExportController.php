<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Exports\ReportsExport;
use App\Services\ReportsServiceException;
use Maatwebsite\Excel\Facades\Excel;
use viki\Service\Models\Elequent\WorkerRecord;
use viki\Service\Models\Elequent\VikiUser;
use viki\Service\Http\Controllers\ReportController;
use Illuminate\Support\Facades\DB;

class ExcelExportController extends Controller
{
    // Rate limiting constants
    private const MAX_EXPORTS_PER_HOUR = 20;
    private const MAX_EXPORT_RECORDS = 10000;

    public function exportReport(Request $request)
    {
        // Enhanced validation with custom rules
        $validated = $request->validate([
            "month_id" => ["required", 'regex:/^(0[1-9]|1[0-2])$/'],
            "year_id" => [
                "required",
                "integer",
                "min:2020",
                "max:" . (date("Y") + 1),
            ],
            "region_id" => ["array", "nullable"],
            "region_id.*" => ["integer", "min:1"],
            "workplace_id" => ["array", "nullable"],
            "workplace_id.*" => ["integer", "min:1"],
            "client_id" => ["array", "nullable"],
            "client_id.*" => ["integer", "min:1"],
            "worker_id" => ["nullable", "integer", "min:1"],
        ]);

        try {
            // Rate limiting check
            if (!$this->checkExportRateLimit()) {
                return response("Too many export requests. Please wait.", 429);
            }

            // Generate report data using optimized service
            $reportsService = app(\App\Services\ReportsService::class);
            $reportData = $reportsService->generateReportData($validated);

            if (
                empty($reportData["workerRecords"]) ||
                $reportData["workerRecords"]->isEmpty()
            ) {
                return response("No data found for the selected criteria", 404);
            }

            // Check record limit for exports
            if (
                $reportData["workerRecords"]->count() > self::MAX_EXPORT_RECORDS
            ) {
                return response(
                    "Too many records ({$reportData["workerRecords"]->count()}). " .
                        "Please use more specific filters. Maximum allowed: " .
                        self::MAX_EXPORT_RECORDS,
                    413
                );
            }

            $filename = $this->generateFilename(
                $validated["month_id"],
                $validated["year_id"]
            );

            // Log export activity
            activity()
                ->performedOn(Auth::user())
                ->causedBy(Auth::user())
                ->withProperties([
                    "filename" => $filename,
                    "record_count" => $reportData["workerRecords"]->count(),
                ])
                ->log("Excel експорт завършен");

            return Excel::download(
                new ReportsExport(
                    $reportData["workerRecords"],
                    $reportData["arraySum"],
                    $reportData["bonusData"],
                    $reportData["penaltyData"],
                    $reportData["vacationData"],
                    $reportData["summary"],
                    $validated["month_id"],
                    $validated["year_id"]
                ),
                $filename
            );
        } catch (\App\Services\ReportsServiceException $e) {
            \Log::warning("Reports service error during export", [
                "user_id" => Auth::id(),
                "filters" => $validated,
                "error" => $e->getMessage(),
            ]);
            return response(
                "Report generation failed: " . $e->getMessage(),
                400
            );
        } catch (\Exception $e) {
            \Log::error("Excel export error", [
                "user_id" => Auth::id(),
                "filters" => $validated,
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString(),
            ]);
            return response("Excel generation failed. Please try again.", 500);
        }
    }

    /**
     * Check export rate limiting
     */
    private function checkExportRateLimit(): bool
    {
        $key = "export_rate_limit:" . Auth::id();
        $exports = \Cache::get($key, 0);

        if ($exports >= self::MAX_EXPORTS_PER_HOUR) {
            return false;
        }

        \Cache::put($key, $exports + 1, 3600); // 1 hour TTL
        return true;
    }

    /**
     * Generate secure filename
     */
    private function generateFilename(string $month, string $year): string
    {
        $sanitizedMonth = preg_replace("/[^0-9]/", "", $month);
        $sanitizedYear = preg_replace("/[^0-9]/", "", $year);
        $timestamp = date("Y-m-d_H-i-s");

        return "справка_за_месец_{$sanitizedMonth}-{$sanitizedYear}_{$timestamp}.xlsx";
    }
}
