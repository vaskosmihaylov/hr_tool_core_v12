<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Exports\PresenceTableExport;
use App\Exports\MonthlyPresenceExport;
use Maatwebsite\Excel\Facades\Excel;
use Viki\Service\Models\Elequent\WorkPlace;
use Viki\Service\Models\Elequent\Worker;
use Viki\Service\Models\Elequent\WorkerRecord;
use Carbon\Carbon;

class PresenceExportController extends Controller
{
    public function exportPresenceTable(Request $request)
    {
        $workplace = $request->get('workplace');
        $date = $request->get('date') ? Carbon::parse($request->get('date')) : Carbon::today();

        $workplaces = WorkPlace::where('status', WorkPlace::WORK_PLACE_ACTIVE)
            ->with('region')
            ->get()
            ->pluck('name', 'id');

        $workers = Worker::where('work_place_id', $workplace)
            ->where('status', Worker::WORKER_ACTIVE)
            ->with(['workplace', 'region'])
            ->get();

        $presenceData = WorkerRecord::where('work_place_id', $workplace)
            ->whereDate('date', $date)
            ->with(['worker', 'activity'])
            ->get()
            ->keyBy('worker_id');

        $workplaceName = str_replace(' ', '_', $workplaces[$workplace] ?? 'workplace');
        $dateString = $date->format('Y-m-d');
        $filename = "presence_table_{$workplaceName}_{$dateString}.xlsx";

        $export = new PresenceTableExport($workers, $presenceData, $workplace, $date);

        return Excel::download($export, $filename);
    }

    public function exportMonthlyPresence(Request $request)
    {
        $workplace = $request->get('workplace');
        $year = $request->get('year') ?: Carbon::now()->year;
        $month = $request->get('month') ?: Carbon::now()->month;

        $workplaces = WorkPlace::where('status', WorkPlace::WORK_PLACE_ACTIVE)
            ->with('region')
            ->get()
            ->pluck('name', 'id');

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        // Get all workers for this workplace
        $workers = Worker::where('work_place_id', $workplace)
            ->where('status', Worker::WORKER_ACTIVE)
            ->get();

        // Get all presence records for the month
        $records = WorkerRecord::where('work_place_id', $workplace)
            ->whereBetween('date', [$startDate, $endDate])
            ->with(['worker', 'activity'])
            ->get()
            ->groupBy('worker_id');

        // Calculate monthly statistics
        $monthlyData = $workers->map(function ($worker) use ($records, $startDate, $endDate) {
            $workerRecords = $records->get($worker->id, collect());
            
            return [
                'worker' => $worker,
                'total_hours' => $workerRecords->sum('hours'),
                'working_days' => $workerRecords->count(),
                'average_hours' => $workerRecords->count() > 0 ? round($workerRecords->sum('hours') / $workerRecords->count(), 2) : 0,
                'records' => $workerRecords->keyBy(fn($record) => Carbon::parse($record->date)->day),
            ];
        });

        $workplaceName = str_replace(' ', '_', $workplaces[$workplace] ?? 'workplace');
        $filename = "monthly_presence_{$workplaceName}_{$year}_{$month}.xlsx";

        $export = new MonthlyPresenceExport($monthlyData, $workplace, $year, $month);

        return Excel::download($export, $filename);
    }
}
