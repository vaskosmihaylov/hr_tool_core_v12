<?php

namespace App\Services;

use App\Services\ReportsServiceException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use viki\Service\Models\Elequent\WorkerRecord;
use viki\Service\Models\Elequent\VikiUser;
use viki\Service\Models\Elequent\HoursActivityByMonth;
use viki\Service\Models\Elequent\WorkPlaceActivity;
use viki\Service\Models\Elequent\WorkPlaceActivityHoursPerDay;
use viki\Service\Models\Elequent\WorkPlaceActivityMonthSnapshot;
use viki\Service\Models\Elequent\WorkerBonus;
use viki\Service\Models\Elequent\Vacation;
use Carbon\Carbon;

/**
 * Optimized Reports Service
 * 
 * Handles report generation with performance optimizations:
 * - Batch database queries to avoid N+1 problems
 * - Caching for expensive calculations
 * - Input validation and sanitization
 * - Memory-efficient processing
 */
class ReportsService
{
    private const CACHE_TTL = 300; // 5 minutes
    private const MAX_RECORDS_LIMIT = 5000; // Prevent memory issues
    
    private array $vacationTypeLabels = [
        1 => 'Платена',
        2 => 'Неплатена', 
        3 => 'Болничен'
    ];

    /**
     * Generate optimized report data with comprehensive error handling
     */
    public function generateReportData(array $filters): array
    {
        // Validate and sanitize inputs
        $validatedFilters = $this->validateFilters($filters);
        
        // Apply role-based region filtering early
        $allowedRegions = $this->getAllowedRegions(Auth::user());
        if (!empty($allowedRegions)) {
            $validatedFilters['region_id'] = array_intersect(
                $validatedFilters['region_id'] ?? [],
                $allowedRegions
            ) ?: $allowedRegions;
        }

        // Generate cache key
        $cacheKey = $this->generateCacheKey($validatedFilters);
        
        // Try cache first for expensive calculations
        if ($cachedData = Cache::get($cacheKey)) {
            return $cachedData;
        }

        // Get worker records with optimized query
        $workerRecords = $this->getWorkerRecords($validatedFilters);
        
        if ($workerRecords->isEmpty()) {
            return [
                'workerRecords' => collect(),
                'arraySum' => [],
                'bonusData' => [],
                'penaltyData' => [],
                'vacationData' => [],
                'summary' => $this->getEmptySummary(),
            ];
        }

        // Check record limit to prevent memory issues
        if ($workerRecords->count() > self::MAX_RECORDS_LIMIT) {
            throw ReportsServiceException::tooManyRecords($workerRecords->count(), self::MAX_RECORDS_LIMIT);
        }

        // Batch calculate all data efficiently
        $salaryData = $this->calculateSalariesBatch($workerRecords, $validatedFilters);
        $bonusData = $this->calculateBonusesBatch($workerRecords, $validatedFilters);
        $penaltyData = $this->calculatePenaltiesBatch($workerRecords, $validatedFilters);
        $vacationData = $this->calculateVacationsBatch($workerRecords, $validatedFilters);

        $result = [
            'workerRecords' => $workerRecords,
            'arraySum' => $salaryData,
            'bonusData' => $bonusData,
            'penaltyData' => $penaltyData,
            'vacationData' => $vacationData,
            'summary' => $this->calculateSummary(
                $workerRecords, 
                $salaryData, 
                $bonusData, 
                $penaltyData, 
                $vacationData
            ),
        ];

        // Cache result for future requests
        Cache::put($cacheKey, $result, self::CACHE_TTL);

        return $result;
    }

    /**
     * Validate and sanitize filter inputs
     */
    private function validateFilters(array $filters): array
    {
        $validated = [];
        
        // Validate month (01-12)
        $validated['month_id'] = sprintf('%02d', max(1, min(12, (int)($filters['month_id'] ?? date('m')))));
        
        // Validate year (reasonable range)
        $currentYear = (int)date('Y');
        $validated['year_id'] = max($currentYear - 5, min($currentYear + 1, (int)($filters['year_id'] ?? $currentYear)));
        
        // Validate arrays and convert to integers
        $validated['region_id'] = $this->validateIntArray($filters['region_id'] ?? []);
        $validated['workplace_id'] = $this->validateIntArray($filters['workplace_id'] ?? []);
        $validated['client_id'] = $this->validateIntArray($filters['client_id'] ?? []);
        
        // Validate single worker ID
        $validated['worker_id'] = !empty($filters['worker_id']) ? (int)$filters['worker_id'] : null;

        return $validated;
    }

    /**
     * Validate array of integers
     */
    private function validateIntArray(mixed $input): array
    {
        if (!is_array($input)) {
            return [];
        }
        
        return array_filter(array_map('intval', $input));
    }

    /**
     * Get regions allowed for current user based on role
     */
    private function getAllowedRegions($user): array
    {
        if ($user->hasRole('supervisor') || $user->hasRole('manager')) {
            return VikiUser::getCurrentUserRegionId($user->id) ?? [];
        }
        
        // Admin users can see all regions
        return [];
    }

    /**
     * Get worker records with optimized single query
     */
    private function getWorkerRecords(array $filters): \Illuminate\Support\Collection
    {
        $user = Auth::user();
        
        // Calculate the correct last day of the month (handles 28, 29, 30, or 31 days)
        $lastDayOfMonth = Carbon::create($filters['year_id'], $filters['month_id'], 1)->endOfMonth()->format('d');

        $query = WorkerRecord::select([
                'viki_worker_records.worker_id',
                'viki_workers.name',
                'viki_workers.family_name',
                'viki_workers.middle_name',
                'viki_workers.egn',
                'viki_worker_records.work_place_id',
                'viki_work_place.name as workPlaceName',
                'viki_work_place.client_id as clId',
                'viki_work_place.region_id as regId',
                DB::raw('SUM(viki_worker_records.hours) as total'),
                DB::raw('GROUP_CONCAT(DISTINCT viki_worker_records.work_place_activity_id) as activities'),
                DB::raw('CONCAT(viki_worker_records.worker_id, "-", viki_worker_records.work_place_id) as unique_id')
            ])
            ->leftJoin('viki_workers', 'viki_workers.id', '=', 'viki_worker_records.worker_id')
            ->leftJoin('viki_work_place', 'viki_work_place.id', '=', 'viki_worker_records.work_place_id')
            ->whereBetween('viki_worker_records.date', [
                $filters['year_id'] . '-' . $filters['month_id'] . '-01',
                $filters['year_id'] . '-' . $filters['month_id'] . '-' . $lastDayOfMonth
            ]);

        // Apply role-based filtering
        if ($user->hasRole('supervisor')) {
            $vikiUser = VikiUser::find($user->id);
            if ($vikiUser) {
                $workplaceIds = $vikiUser->workPlaces()->pluck('id')->toArray();
                $query->whereIn('viki_worker_records.work_place_id', $workplaceIds);
            }
        }

        // Apply filters
        if (!empty($filters['workplace_id'])) {
            $query->whereIn('viki_worker_records.work_place_id', $filters['workplace_id']);
        }

        if (!empty($filters['region_id'])) {
            $query->whereIn('viki_work_place.region_id', $filters['region_id']);
        }

        if (!empty($filters['client_id'])) {
            $query->whereIn('viki_work_place.client_id', $filters['client_id']);
        }

        if (!empty($filters['worker_id'])) {
            $query->where('viki_worker_records.worker_id', $filters['worker_id']);
        }

        $query->groupBy([
            'viki_worker_records.worker_id', 
            'viki_worker_records.work_place_id',
            'viki_workers.name',
            'viki_workers.family_name',
            'viki_workers.middle_name',
            'viki_workers.egn',
            'viki_work_place.name',
            'viki_work_place.client_id',
            'viki_work_place.region_id'
        ])
        ->orderBy('viki_workers.name');

        return $query->get();
    }

    /**
     * Calculate salaries in batch to avoid N+1 queries
     */
    private function calculateSalariesBatch($workerRecords, array $filters): array
    {
        $salaries = [];
        $year = (int) $filters['year_id'];
        $month = (int) $filters['month_id'];
        $normalizedDate = sprintf('%04d-%02d-01', $year, $month);
        $workingDaysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year) -
            count($this->getAllNonWorkingDays($month, $year));

        // Calculate the correct last day of the month
        $lastDayOfMonth = Carbon::create($year, $month, 1)->endOfMonth()->format('d');

        // Get all unique activity IDs
        $activityIds = $workerRecords->flatMap(function ($record) {
            return explode(',', $record->activities);
        })->map(fn ($id) => (int) $id)->unique()->filter()->values();

        $workplaceIds = $workerRecords
            ->pluck('work_place_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->filter()
            ->values();

        // Batch load all workplace activities
        $activities = WorkPlaceActivity::whereIn('id', $activityIds)->get()->keyBy('id');
        $hoursByMonth = HoursActivityByMonth::whereIn('work_place_activity_id', $activityIds)
            ->where('date', $normalizedDate)
            ->get()
            ->keyBy('work_place_activity_id');
        $hoursPerDayByActivity = WorkPlaceActivityHoursPerDay::whereIn('work_place_activity_id', $activityIds)
            ->get()
            ->keyBy('work_place_activity_id');
        $snapshots = WorkPlaceActivityMonthSnapshot::whereIn('work_place_id', $workplaceIds)
            ->where('date', $normalizedDate)
            ->get()
            ->keyBy(fn ($snapshot) => $snapshot->work_place_id . '-' . $snapshot->base_activity_id);
        $activityHours = WorkerRecord::select([
                'worker_id',
                'work_place_id',
                'work_place_activity_id',
                DB::raw('SUM(hours) as total_hours'),
            ])
            ->whereBetween('date', [
                $year . '-' . sprintf('%02d', $month) . '-01',
                $year . '-' . sprintf('%02d', $month) . '-' . $lastDayOfMonth,
            ])
            ->whereIn('work_place_activity_id', $activityIds)
            ->whereIn('work_place_id', $workplaceIds)
            ->groupBy('worker_id', 'work_place_id', 'work_place_activity_id')
            ->get()
            ->keyBy(
                fn ($row) => $row->worker_id . '-' . $row->work_place_id . '-' . $row->work_place_activity_id
            );

        foreach ($workerRecords as $record) {
            $recordActivities = array_filter(explode(',', $record->activities));
            $totalSalary = 0;

            foreach ($recordActivities as $activityId) {
                $activityId = (int) $activityId;
                $activity = $activities->get($activityId);
                if (!$activity) continue;

                $snapshot = $snapshots->get($record->work_place_id . '-' . $activityId);
                $workingHours = $this->getActivityWorkingHoursForMonth(
                    $activity,
                    $workingDaysInMonth,
                    $hoursByMonth->get($activityId),
                    $hoursPerDayByActivity->get($activityId)?->hours_per_day,
                    $snapshot?->hours_per_day
                );
                $salary = $snapshot?->neto_salary ?? (float) $activity->neto_salary;
                $hourPrice = $workingHours > 0 ? ((float) $salary / $workingHours) : 0;
                $hoursKey = $record->worker_id . '-' . $record->work_place_id . '-' . $activityId;
                $workedHours = (float) ($activityHours->get($hoursKey)?->total_hours ?? 0);

                $totalSalary += $hourPrice * $workedHours;
            }

            $salaries[$record->unique_id] = $totalSalary;
        }

        return $salaries;
    }

    /**
     * Calculate bonuses in batch
     */
    private function calculateBonusesBatch($workerRecords, array $filters): array
    {
        $workerWorkplacePairs = $workerRecords->map(function ($record) {
            return [
                'worker_id' => $record->worker_id,
                'work_place_id' => $record->work_place_id,
                'unique_id' => $record->unique_id
            ];
        });

        $bonuses = WorkerBonus::whereIn('worker_id', $workerRecords->pluck('worker_id'))
            ->where('type', 0) // BONUS
            ->whereYear('for_month', $filters['year_id'])
            ->whereMonth('for_month', $filters['month_id'])
            ->get()
            ->groupBy(function ($bonus) {
                return $bonus->worker_id . '-' . $bonus->work_place_id;
            });

        $result = [];
        foreach ($workerWorkplacePairs as $pair) {
            $key = $pair['worker_id'] . '-' . $pair['work_place_id'];
            $result[$pair['unique_id']] = $bonuses->get($key, collect())->sum('sum');
        }

        return $result;
    }

    private function getActivityWorkingHoursForMonth(
        WorkPlaceActivity $activity,
        int $workingDaysInMonth,
        ?HoursActivityByMonth $hoursConfig = null,
        $configuredHoursPerDay = null,
        $snapshotHoursPerDay = null
    ): float {
        $hoursPerDay = $snapshotHoursPerDay !== null
            ? (int) $snapshotHoursPerDay
            : (int) $configuredHoursPerDay;

        if (
            $hoursPerDay <= 0 &&
            preg_match('/(\d+)\s*ч/u', (string) $activity->activity, $matches)
        ) {
            $hoursPerDay = (int) ($matches[1] ?? 0);
        }

        if ($hoursPerDay <= 0) {
            $hoursPerDay = 8;
        }

        $calculatedHours = $workingDaysInMonth * $hoursPerDay;

        if (
            $hoursConfig &&
            (int) $activity->type_working === WorkPlaceActivity::WORKING_BY_HOURS
        ) {
            return (float) $hoursConfig->hours_for_person;
        }

        if ((int) $activity->type_working === WorkPlaceActivity::WORKING_STANDART) {
            return (float) $calculatedHours;
        }

        return (float) $calculatedHours;
    }

    private function getAllNonWorkingDays(int $month, int $year): array
    {
        $specialDays = DB::table('viki_special_days')
            ->where('date', 'like', sprintf('%04d-%02d-%%', $year, $month))
            ->pluck('date')
            ->map(fn ($date) => (int) substr($date, strrpos($date, '-') + 1))
            ->all();

        $weekendDays = [];
        foreach (range(1, cal_days_in_month(CAL_GREGORIAN, $month, $year)) as $day) {
            if (date('N', strtotime(sprintf('%d-%02d-%02d', $year, $month, $day))) >= 6) {
                $weekendDays[] = $day;
            }
        }

        foreach ($specialDays as $specialDay) {
            if (!in_array($specialDay, $weekendDays, true)) {
                $weekendDays[] = $specialDay;
            }
        }

        return $weekendDays;
    }

    /**
     * Calculate penalties in batch
     */
    private function calculatePenaltiesBatch($workerRecords, array $filters): array
    {
        $workerWorkplacePairs = $workerRecords->map(function ($record) {
            return [
                'worker_id' => $record->worker_id,
                'work_place_id' => $record->work_place_id,
                'unique_id' => $record->unique_id
            ];
        });

        $penalties = WorkerBonus::whereIn('worker_id', $workerRecords->pluck('worker_id'))
            ->where('type', 1) // PENALTY
            ->whereYear('for_month', $filters['year_id'])
            ->whereMonth('for_month', $filters['month_id'])
            ->get()
            ->groupBy(function ($penalty) {
                return $penalty->worker_id . '-' . $penalty->work_place_id;
            });

        $result = [];
        foreach ($workerWorkplacePairs as $pair) {
            $key = $pair['worker_id'] . '-' . $pair['work_place_id'];
            $result[$pair['unique_id']] = $penalties->get($key, collect())->sum('sum');
        }

        return $result;
    }

    /**
     * Calculate vacations in batch with optimized date queries
     */
    private function calculateVacationsBatch($workerRecords, array $filters): array
    {
        $startOfMonth = Carbon::create($filters['year_id'], $filters['month_id'], 1)->startOfMonth();
        $endOfMonth = Carbon::create($filters['year_id'], $filters['month_id'], 1)->endOfMonth();

        // Batch load all vacation data
        $vacations = Vacation::whereIn('worker_id', $workerRecords->pluck('worker_id'))
            ->where('status', 1) // Only approved
            ->where(function($query) use ($startOfMonth, $endOfMonth) {
                $query->where(function($q) use ($startOfMonth, $endOfMonth) {
                    $q->whereBetween('start_date', [$startOfMonth, $endOfMonth]);
                })->orWhere(function($q) use ($startOfMonth, $endOfMonth) {
                    $q->whereBetween('end_date', [$startOfMonth, $endOfMonth]);
                })->orWhere(function($q) use ($startOfMonth, $endOfMonth) {
                    $q->where('start_date', '<=', $startOfMonth)
                      ->where('end_date', '>=', $endOfMonth);
                });
            })
            ->get()
            ->groupBy('worker_id');

        $result = [];
        foreach ($workerRecords as $record) {
            $workerVacations = $vacations->get($record->worker_id, collect());
            $totalDays = 0;
            $details = [];

            foreach ($workerVacations as $vacation) {
                // Normalize dates to start of day to avoid float precision issues
                $vacationStart = Carbon::parse($vacation->start_date)->startOfDay();
                $vacationEnd = Carbon::parse($vacation->end_date)->startOfDay();

                $overlapStart = $vacationStart->max($startOfMonth->copy()->startOfDay());
                $overlapEnd = $vacationEnd->min($endOfMonth->copy()->startOfDay());

                if ($overlapStart <= $overlapEnd) {
                    // Use diffInDays() and add 1 to include both start and end days
                    // Round to handle any floating-point precision issues
                    $daysInMonth = (int) round($overlapStart->diffInDays($overlapEnd)) + 1;
                    $totalDays += $daysInMonth;

                    $details[] = [
                        'days' => $daysInMonth,
                        'type' => $this->vacationTypeLabels[$vacation->type] ?? 'Неизвестен',
                        'start_date' => $overlapStart->format('d.m.Y'),
                        'end_date' => $overlapEnd->format('d.m.Y')
                    ];
                }
            }

            $result[$record->unique_id] = [
                'total_days' => $totalDays,
                'details' => $details
            ];
        }

        return $result;
    }

    /**
     * Calculate summary statistics
     */
    private function calculateSummary($workerRecords, $salaryData, $bonusData, $penaltyData, $vacationData): array
    {
        return [
            'total_workers' => $workerRecords->unique('worker_id')->count(),
            'total_records' => $workerRecords->count(),
            'total_hours' => $workerRecords->sum('total'),
            'total_salary' => array_sum($salaryData),
            'total_bonus' => array_sum($bonusData),
            'total_penalty' => array_sum($penaltyData),
            'total_vacation_days' => array_sum(array_column($vacationData, 'total_days')),
        ];
    }

    /**
     * Get empty summary for no results
     */
    private function getEmptySummary(): array
    {
        return [
            'total_workers' => 0,
            'total_records' => 0,
            'total_hours' => 0,
            'total_salary' => 0,
            'total_bonus' => 0,
            'total_penalty' => 0,
            'total_vacation_days' => 0,
        ];
    }

    /**
     * Generate cache key for results
     */
    private function generateCacheKey(array $filters): string
    {
        $user = Auth::user();
        $keyData = [
            'version' => 'monthly-presence-aligned-v2',
            'user_id' => $user->id,
            'user_roles' => $user->roles->pluck('name')->sort()->implode(','),
            'filters' => $filters
        ];
        
        return 'reports:' . md5(serialize($keyData));
    }

}
