<?php

namespace App\Filament\Service\Resources\PresenceResource\Pages;

use App\Filament\Service\Resources\PresenceResource;
use App\Filament\Service\Resources\WorkPlaceResource;
use App\Services\Presence\PresenceConfigurationService;
use Filament\Resources\Pages\Page;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use viki\Service\Models\Elequent\WorkPlace;
use viki\Service\Models\Elequent\Worker;
use viki\Service\Models\Elequent\WorkerRecord;
use viki\Service\Models\Elequent\WorkPlaceActivity;
use viki\Service\Models\Elequent\WorkPlaceActivityHoursPerDay;
use viki\Service\Models\Elequent\Vacation;
use viki\Service\Models\Elequent\VikiUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use viki\Service\Models\Elequent\Approvement;
use viki\Service\Models\Elequent\SpecialDay;
use viki\Service\Models\Elequent\WorkPlaceActivityMonthSnapshot;

class MonthlyPresence extends Page
{
    protected static string $resource = PresenceResource::class;
    protected static string $view = "filament.service.resources.presence-resource.pages.monthly-presence";
    protected ?string $maxContentWidth = "full";

    // Route parameters
    public int $workplace;
    public ?int $year = null;
    public ?int $month = null;

    // Data properties
    public $workplaces;
    public $monthlyData;
    public $workplaceData;
    public $vacationData = [];

    // State properties
    public $isLocked = false;
    public $hoursData = [];
    public $hasUnsavedChanges = false;

    /** @var array<int, array{label: string, type: int}> */
    private ?array $cachedSpecialDays = null;

    public function mount(int $workplace, ?string $date = null): void
    {
        $this->workplace = $workplace;
        $this->parseDateParameter($date);
        $this->loadData();
        $this->initializeHoursData();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getBackAction(),
            $this->getPreviousMonthAction(),
            $this->getCurrentMonthAction(),
            $this->getNextMonthAction(),
            // Removed: $this->getSaveAction() - auto-save implemented
            $this->getLockAction(),
            $this->getUnlockAction(),
            $this->getExportAction(),
            $this->getPrintAction(),
        ];
    }

    public function getHeader(): ?View
    {
        return view(
            "filament.service.resources.monthly-presence-resource.pages.partials.header"
        );
    }

    // Navigation methods
    public function changeMonth(int $months): void
    {
        if ($this->hasUnsavedChanges) {
            $this->showUnsavedChangesWarning();
            return;
        }

        $date = Carbon::create($this->year, $this->month, 1)->addMonths(
            $months
        );
        $dateString = sprintf("%02d-%d", $date->month, $date->year);
        $this->redirect(
            "/service/presences/monthly/{$this->workplace}/{$dateString}"
        );
    }

    public function goToCurrentMonth(): void
    {
        if ($this->hasUnsavedChanges) {
            $this->showUnsavedChangesWarning();
            return;
        }

        $now = Carbon::now();
        $dateString = sprintf("%02d-%d", $now->month, $now->year);
        $this->redirect(
            "/service/presences/monthly/{$this->workplace}/{$dateString}"
        );
    }

    // Hours management
    public function updatedHoursData(): void
    {
        $this->hasUnsavedChanges = true;
    }

    public function saveHours(): void
    {
        try {
            $this->ensureMonthIsUnlocked();

            DB::beginTransaction();

            // Process the hours data and prepare for budget checking
            $processedData = $this->prepareHoursDataForBudgetCheck();

            // Check if the changes exceed budget
            $budgetCheck = $this->checkIfInBudget($processedData);

            if ($budgetCheck["inBudget"] === true) {
                // Within budget - save all records as approved
                $this->processHoursData();
            } else {
                // Budget exceeded - create approval request
                $approvalId = $this->createApproveRequest(
                    $budgetCheck["overBudget"]
                );

                // Save records with proper approval handling
                $this->processHoursDataWithApproval(
                    $processedData,
                    $budgetCheck,
                    $approvalId
                );
            }

            DB::commit();

            $this->hasUnsavedChanges = false;
            $this->reloadData();

            if ($budgetCheck["inBudget"] === false) {
                $this->showWarningNotification(
                    "Часовете са запазени, но надвишават бюджета с {$budgetCheck["overBudget"]} €. Създадено е искане за одобрение. Можете да го видите в секция 'Одобрения'."
                );
            } else {
                $this->showSuccessNotification(
                    "Часовете са запазени успешно в рамките на бюджета."
                );
            }
        } catch (\Exception $e) {
            DB::rollBack();
            $this->showErrorNotification(
                "Грешка при запазване: " . $e->getMessage()
            );
        }
    }

    public function removeWorkerFromMonth($workerId): void
    {
        try {
            $this->ensureMonthIsUnlocked();

            DB::beginTransaction();

            // Delete worker hours records
            $this->deleteWorkerRecords($workerId);

            // Remove worker from pivot tables for this specific month
            $monthStart = $this->getMonthStartDate()->toDateString();

            // Remove from workplace pivot
            DB::table("viki_work_place_worker")
                ->where("work_place_id", $this->workplace)
                ->where("worker_id", $workerId)
                ->where("date", $monthStart)
                ->delete();

            // Remove from activity pivots for this workplace and month
            $activityIds = WorkPlaceActivity::where(
                "work_place_id",
                $this->workplace
            )
                ->whereNull("date")
                ->where("copied", WorkPlaceActivity::NOT_COPIED_ACTIVITY)
                ->pluck("id");

            DB::table("viki_work_place_activity_worker")
                ->where("worker_id", $workerId)
                ->whereIn("work_place_activity_id", $activityIds)
                ->where("date", $monthStart)
                ->delete();

            DB::commit();

            $this->reloadData();
            $this->showSuccessNotification("Работникът е премахнат успешно.");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->showErrorNotification(
                "Грешка при премахване: " . $e->getMessage()
            );
        }
    }

    // Month locking
    public function lockMonth(): void
    {
        DB::table("viki_monthly_presence_locks")->updateOrInsert(
            [
                "work_place_id" => $this->workplace,
                "year" => $this->year,
                "month" => $this->month,
            ],
            [
                "is_locked" => true,
                "locked_by" => Auth::id(),
                "locked_at" => now(),
                "updated_at" => now(),
            ]
        );

        $this->isLocked = true;

        // Snapshot activity data so locked months are not affected by future edits.
        $normalizedDate = sprintf("%04d-%02d-01", $this->year, $this->month);
        WorkPlaceActivityMonthSnapshot::snapshotActivities(
            $this->workplace,
            $normalizedDate,
            Auth::id()
        );

        // Copy worker/activity assignments to next month, but keep next-month hours empty.
        $this->copyWorkersToNextMonth();

        $this->showSuccessNotification(
            "Месецът е заключен успешно. Работниците са копирани за следващия месец."
        );
    }

    public function unlockMonth(): void
    {
        DB::table("viki_monthly_presence_locks")->updateOrInsert(
            [
                "work_place_id" => $this->workplace,
                "year" => $this->year,
                "month" => $this->month,
            ],
            [
                "is_locked" => false,
                "unlocked_by" => Auth::id(),
                "unlocked_at" => now(),
                "updated_at" => now(),
            ]
        );

        $this->isLocked = false;

        // Remove snapshots so the month uses live activity data again.
        $normalizedDate = sprintf("%04d-%02d-01", $this->year, $this->month);
        WorkPlaceActivityMonthSnapshot::where("work_place_id", $this->workplace)
            ->where("date", $normalizedDate)
            ->delete();

        $this->reloadData();

        $this->showSuccessNotification("Месецът е отключен успешно.");
    }

    private function copyWorkersToNextMonth(): void
    {
        try {
            DB::beginTransaction();

            $currentMonthStart = Carbon::create(
                $this->year,
                $this->month,
                1
            )->startOfMonth();
            $nextMonthStart = $currentMonthStart
                ->copy()
                ->addMonth()
                ->startOfMonth();
            $nextMonthEnd = $nextMonthStart->copy()->endOfMonth();

            $baseActivityIds = WorkPlaceActivity::query()
                ->where("work_place_id", $this->workplace)
                ->whereNull("date")
                ->where("copied", WorkPlaceActivity::NOT_COPIED_ACTIVITY)
                ->pluck("id");

            if ($baseActivityIds->isEmpty()) {
                DB::commit();
                return;
            }

            // Replace next-month assignment state so relocking always recreates a clean month.
            DB::table("viki_work_place_activity_worker")
                ->whereIn("work_place_activity_id", $baseActivityIds)
                ->where("date", $nextMonthStart->toDateString())
                ->delete();

            DB::table("viki_work_place_worker")
                ->where("work_place_id", $this->workplace)
                ->where("date", $nextMonthStart->toDateString())
                ->delete();

            // Keep next month empty in the monthly table.
            WorkerRecord::query()
                ->where("work_place_id", $this->workplace)
                ->whereBetween("date", [
                    $nextMonthStart->toDateString(),
                    $nextMonthEnd->toDateString(),
                ])
                ->delete();

            $copiedWorkersPerActivity = 0;
            $allCopiedWorkerIds = collect();

            foreach ($baseActivityIds as $activityId) {
                $workerIds = DB::table("viki_work_place_activity_worker")
                    ->where("work_place_activity_id", $activityId)
                    ->where("date", $currentMonthStart->toDateString())
                    ->pluck("worker_id");

                if ($workerIds->isEmpty()) {
                    continue;
                }

                foreach ($workerIds as $workerId) {
                    DB::table(
                        "viki_work_place_activity_worker"
                    )->insertOrIgnore([
                        "work_place_activity_id" => $activityId,
                        "worker_id" => $workerId,
                        "date" => $nextMonthStart->toDateString(),
                    ]);

                    $allCopiedWorkerIds->push((int) $workerId);
                    $copiedWorkersPerActivity++;
                }
            }

            foreach ($allCopiedWorkerIds->unique() as $workerId) {
                DB::table("viki_work_place_worker")->insertOrIgnore([
                    "work_place_id" => $this->workplace,
                    "worker_id" => $workerId,
                    "date" => $nextMonthStart->toDateString(),
                ]);
            }

            DB::commit();

            \Log::info(
                "Copied {$copiedWorkersPerActivity} activity-worker assignments to next month for workplace {$this->workplace}",
                [
                    "current_month" => $currentMonthStart->toDateString(),
                    "next_month" => $nextMonthStart->toDateString(),
                    "distinct_workers" => $allCopiedWorkerIds
                        ->unique()
                        ->count(),
                ]
            );
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            \Log::error(
                "Error copying workers to next month: " . $e->getMessage()
            );
        }
    }

    private function ensureMonthIsUnlocked(): void
    {
        if (
            PresenceConfigurationService::isMonthLocked(
                $this->workplace,
                $this->getMonthStartDate()
            )
        ) {
            $this->isLocked = true;

            throw new \RuntimeException(
                "Месецът е заключен. Отключете месеца, за да правите промени."
            );
        }
    }

    // Export
    public function exportMonthlyExcel(): void
    {
        if (!$this->monthlyData || $this->monthlyData->isEmpty()) {
            $this->showErrorNotification("Няма данни за експорт");
            return;
        }

        $params = [
            "workplace" => $this->workplace,
            "year" => $this->year,
            "month" => $this->month,
        ];
        $url = route("service.presence.export-monthly", $params);
        $this->js("window.open('$url', '_blank')");
        $this->showSuccessNotification(
            "Excel файлът се генерира в нов прозорец..."
        );
    }

    // Vacation methods
    public function getVacationTypeInfo($type): array
    {
        $types = [
            Vacation::PAYD_VACATION => [
                "label" => "Платена отпуска",
                "short" => "ПО",
                "color" => "green",
                "style" =>
                    "background-color: #dcfce7; border-color: #16a34a; color: #166534;",
            ],
            Vacation::NOT_PAYD_VACATION => [
                "label" => "Неплатена отпуска",
                "short" => "НО",
                "color" => "blue",
                "style" =>
                    "background-color: #dbeafe; border-color: #2563eb; color: #1d4ed8;",
            ],
            Vacation::HOSPITAL_SHEET => [
                "label" => "Болничен",
                "short" => "БЛ",
                "color" => "red",
                "style" =>
                    "background-color: #fee2e2; border-color: #dc2626; color: #991b1b;",
            ],
        ];

        return $types[$type] ?? [
            "label" => "Неизвестен тип",
            "short" => "?",
            "color" => "gray",
            "style" =>
                "background-color: #f3f4f6; border-color: #6b7280; color: #374151;",
        ];
    }

    public function getVacationTypesLegend(): array
    {
        return [
            Vacation::PAYD_VACATION => $this->getVacationTypeInfo(
                Vacation::PAYD_VACATION
            ),
            Vacation::NOT_PAYD_VACATION => $this->getVacationTypeInfo(
                Vacation::NOT_PAYD_VACATION
            ),
            Vacation::HOSPITAL_SHEET => $this->getVacationTypeInfo(
                Vacation::HOSPITAL_SHEET
            ),
        ];
    }

    // Utility methods
    public function getTitle(): string
    {
        $workplaceName =
            $this->workplaces[$this->workplace] ?? "Неизвестно място";
        return "Месечно управление - {$workplaceName} - {$this->getMonthName()}";
    }

    public function getMonthName(): string
    {
        $months = [
            1 => "Януари",
            2 => "Февруари",
            3 => "Март",
            4 => "Април",
            5 => "Май",
            6 => "Юни",
            7 => "Юли",
            8 => "Август",
            9 => "Септември",
            10 => "Октомври",
            11 => "Ноември",
            12 => "Декември",
        ];

        return $months[$this->month] . " " . $this->year;
    }

    public function getWorkplaceBudgetSummary(): array
    {
        $monthKey = sprintf("%02d-%d", $this->month, $this->year);
        $normalizedDate = sprintf("%04d-%02d-01", $this->year, $this->month);

        $workPlace = WorkPlace::with([
            "overBudget" => function ($q) use ($normalizedDate) {
                $q->where("viki_workplace_month_budget.date", $normalizedDate);
            },
        ])->find($this->workplace);

        $budget = $workPlace
            ? (float) $workPlace->getBudgetByDate($monthKey)
            : 0.0;

        if ($workPlace && $workPlace->overBudget->count() > 0) {
            $budget += (float) $workPlace->overBudget->first()->sum_up;
        }

        $paid = collect($this->monthlyData ?? [])->sum(function (
            $activityGroup
        ) {
            return (float) ($activityGroup["group_totals"]["used_budget"] ?? 0);
        });

        return [
            "paid" => round($paid, 2),
            "budget" => round($budget, 2),
            "exceeded" => $paid > $budget,
        ];
    }

    public function getDaysInMonth(): int
    {
        return Carbon::create($this->year, $this->month, 1)->daysInMonth;
    }

    // Private helper methods
    private function parseDateParameter(?string $date): void
    {
        if ($date && count($dateParts = explode("-", $date)) === 2) {
            $this->month = (int) $dateParts[0];
            $this->year = (int) $dateParts[1];
        } else {
            $now = Carbon::now();
            $this->year = $now->year;
            $this->month = $now->month;
        }
    }

    private function loadData(): void
    {
        $this->cachedSpecialDays = null;

        $this->loadUserWorkplaces();

        if (!$this->workplace || !$this->workplaces->has($this->workplace)) {
            if ($this->workplace) {
                abort(403, "Нямате достъп до този обект");
            }
            return;
        }

        $this->workplaceData = WorkPlace::with("region", "client")->find(
            $this->workplace
        );
        $this->loadLockStatus();
        $this->loadVacationData();
        $this->loadMonthlyData();
    }

    private function loadUserWorkplaces(): void
    {
        $user = VikiUser::find(Auth::id());

        $workplaces = match (true) {
            Auth::user()->hasRole(["admin", "super_admin"]) => WorkPlace::where(
                "status",
                WorkPlace::WORK_PLACE_ACTIVE
            )
                ->with("region")
                ->orderBy("name")
                ->get(),
            Auth::user()->hasRole("manager") => WorkPlace::where(
                "status",
                WorkPlace::WORK_PLACE_ACTIVE
            )
                ->whereIn(
                    "region_id",
                    VikiUser::getCurrentUserRegionId(Auth::id())
                )
                ->with("region")
                ->orderBy("name")
                ->get(),
            Auth::user()->hasRole("supervisor") => $user
                ->activeWorkPlaces()
                ->with("region")
                ->orderBy("name")
                ->get(),
            default => collect(),
        };

        $this->workplaces = $workplaces->pluck("name", "id");
    }

    private function loadLockStatus(): void
    {
        $lock = DB::table("viki_monthly_presence_locks")
            ->where("work_place_id", $this->workplace)
            ->where("year", $this->year)
            ->where("month", $this->month)
            ->first();

        $this->isLocked = $lock ? (bool) $lock->is_locked : false;
    }

    private function loadMonthlyData(): void
    {
        $start = $this->getMonthStartDate();
        $end = $start->copy()->endOfMonth();
        $normalizedDate = sprintf("%04d-%02d-01", $this->year, $this->month);

        // Use only base activities for monthly presence management.
        $activities = WorkPlaceActivity::where(
            "work_place_id",
            $this->workplace
        )
            ->whereNull("date")
            ->where("copied", WorkPlaceActivity::NOT_COPIED_ACTIVITY)
            ->orderBy("activity")
            ->get();

        // Load snapshots for this month (only populated for locked months).
        $snapshots = WorkPlaceActivityMonthSnapshot::getForMonth(
            $this->workplace,
            $normalizedDate
        );

        $groupedByActivity = [];

        foreach ($activities as $activity) {
            // If a snapshot exists for this activity, overlay its values so that
            // salary/name/worker_count changes made after locking are ignored.
            $snapshot = $snapshots->get($activity->id);
            $effectiveSalary = $snapshot
                ? (float) $snapshot->neto_salary
                : (float) $activity->neto_salary;
            $effectiveName = $snapshot
                ? $snapshot->activity
                : $activity->activity;
            $effectiveCount = $snapshot
                ? (int) $snapshot->worker_count
                : (int) ($activity->worker_count ?? 0);
            $effectiveType = $snapshot
                ? (int) $snapshot->type_working
                : (int) $activity->type_working;
            $snapshotHoursPerDay = $snapshot ? $snapshot->hours_per_day : null;

            $records = WorkerRecord::where("work_place_id", $this->workplace)
                ->where("work_place_activity_id", $activity->id)
                ->whereBetween("date", [
                    $start->toDateString(),
                    $end->toDateString(),
                ])
                ->with("worker")
                ->get()
                ->groupBy("worker_id");

            $pivotWorkerIds = $activity
                ->temporaryWorkers()
                ->wherePivot("date", $start->toDateString())
                ->pluck("viki_workers.id");

            $workerIds = $records
                ->keys()
                ->merge($pivotWorkerIds)
                ->unique()
                ->sort()
                ->values();

            $monthKey = sprintf("%02d-%d", $this->month, $this->year);
            $hourRate = $this->getHourCostOnWorkPlaceActivityByDate(
                $activity,
                $monthKey,
                $effectiveSalary,
                $snapshotHoursPerDay
            );
            $monthlyHours = $this->getActivityWorkingHoursForDate(
                $activity,
                $monthKey,
                $snapshotHoursPerDay
            );
            $maxBudget = $effectiveSalary * $effectiveCount;
            $maxHours = $monthlyHours * $effectiveCount;

            $groupedByActivity[$activity->id] = [
                "activity" => $activity,
                "activity_name" => $effectiveName,
                "activity_salary" => $effectiveSalary,
                "hour_rate" => $hourRate,
                "workers" => [],
                "group_totals" => [
                    "used_budget" => 0,
                    "max_budget" => $maxBudget,
                    "used_hours" => 0,
                    "max_hours" => $maxHours,
                ],
            ];

            foreach ($workerIds as $workerId) {
                $worker = $records->has($workerId)
                    ? $records[$workerId]->first()->worker
                    : Worker::find($workerId);

                if (!$worker) {
                    continue;
                }

                $workerRecords = $records->get($workerId, collect());
                $hasWorkerRecords = $workerRecords->isNotEmpty();

                if (
                    !$hasWorkerRecords &&
                    $worker->status !== Worker::WORKER_ACTIVE
                ) {
                    continue;
                }

                $recordsByDay = $workerRecords->keyBy(
                    fn($record) => Carbon::parse($record->date)->day
                );

                $totalHours = 0;
                $dailyRecords = [];

                for ($day = 1; $day <= $this->getDaysInMonth(); $day++) {
                    $dailyRecords[$day] = $recordsByDay->get($day);
                    if ($dailyRecords[$day]) {
                        $totalHours += $dailyRecords[$day]->hours;
                    }
                }

                $calculatedPrice = $this->calculateWorkerPriceForActivity(
                    $activity,
                    $totalHours,
                    $hourRate
                );
                $calculatedTotal = $this->calculateWorkerTotalForActivity(
                    $activity,
                    $totalHours
                );
                $roundedHours = round($totalHours, 2);

                $groupedByActivity[$activity->id]["workers"][] = [
                    "worker" => $worker,
                    "total_hours" => $roundedHours,
                    "working_days" => $workerRecords->count(),
                    "average_hours" => $this->calculateAverageHours(
                        $workerRecords
                    ),
                    "records" => collect($dailyRecords),
                    "calculated_price" => $calculatedPrice,
                    "calculated_total" => $calculatedTotal,
                ];

                $groupedByActivity[$activity->id]["group_totals"][
                    "used_budget"
                ] += $calculatedPrice;
                $groupedByActivity[$activity->id]["group_totals"][
                    "used_hours"
                ] += $roundedHours;
            }

            $groupedByActivity[$activity->id]["group_totals"][
                "used_budget"
            ] = round(
                $groupedByActivity[$activity->id]["group_totals"][
                    "used_budget"
                ],
                2
            );
            $groupedByActivity[$activity->id]["group_totals"][
                "used_hours"
            ] = round(
                $groupedByActivity[$activity->id]["group_totals"]["used_hours"],
                2
            );
            $groupedByActivity[$activity->id]["group_totals"][
                "max_budget"
            ] = round(
                $groupedByActivity[$activity->id]["group_totals"]["max_budget"],
                2
            );
            $groupedByActivity[$activity->id]["group_totals"][
                "max_hours"
            ] = round(
                $groupedByActivity[$activity->id]["group_totals"]["max_hours"],
                2
            );
        }

        $this->monthlyData = collect($groupedByActivity);
    }

    private function loadVacationData(): void
    {
        if (!$this->workplace) {
            return;
        }

        $dateRange = $this->getMonthDateRange();
        $start = $this->getMonthStartDate();

        // Use only base activities for monthly presence management.
        $activities = WorkPlaceActivity::where(
            "work_place_id",
            $this->workplace
        )
            ->whereNull("date")
            ->where("copied", WorkPlaceActivity::NOT_COPIED_ACTIVITY)
            ->get();

        // Collect all worker IDs assigned to these activities (from pivot and records)
        $workerIds = collect();
        $end = $start->copy()->endOfMonth();
        foreach ($activities as $activity) {
            // Get workers from pivot table for this month
            $pivotWorkerIds = $activity
                ->temporaryWorkers()
                ->wherePivot("date", $start->toDateString())
                ->pluck("viki_workers.id");

            // Also get workers who have records for this activity/month
            $recordWorkerIds = WorkerRecord::where(
                "work_place_id",
                $this->workplace
            )
                ->where("work_place_activity_id", $activity->id)
                ->whereBetween("date", [
                    $start->toDateString(),
                    $end->toDateString(),
                ])
                ->distinct()
                ->pluck("worker_id");

            $workerIds = $workerIds
                ->merge($pivotWorkerIds)
                ->merge($recordWorkerIds);
        }

        $workerIds = $workerIds->unique();

        if ($workerIds->isEmpty()) {
            $this->vacationData = [];
            return;
        }

        // Get vacations for these workers that overlap with the current month
        [$startDate, $endDate] = $dateRange;
        $vacations = Vacation::whereIn("worker_id", $workerIds->toArray())
            ->where("status", 1) // Only active/approved vacations
            ->where(function ($query) use ($startDate, $endDate) {
                $query
                    ->whereBetween("start_date", [
                        $startDate->toDateString(),
                        $endDate->toDateString(),
                    ])
                    ->orWhereBetween("end_date", [
                        $startDate->toDateString(),
                        $endDate->toDateString(),
                    ])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where(
                            "start_date",
                            "<=",
                            $startDate->toDateString()
                        )->where("end_date", ">=", $endDate->toDateString());
                    });
            })
            ->with("worker")
            ->get();

        $this->vacationData = [];

        foreach ($vacations as $vacation) {
            $this->processVacationDays($vacation, $dateRange);
        }
    }

    private function initializeHoursData(): void
    {
        $this->hoursData = [];

        if (!$this->monthlyData) {
            return;
        }

        foreach ($this->monthlyData as $activityId => $activityGroup) {
            $this->hoursData[$activityId] = [];

            // Access workers as array since we're using arrays inside collections
            foreach ($activityGroup["workers"] as $data) {
                $workerId = $data["worker"]->id;
                $this->hoursData[$activityId][$workerId] = [];

                for ($day = 1; $day <= $this->getDaysInMonth(); $day++) {
                    // Load hours for all days, including vacation days
                    // This allows workers to work during their vacation if needed
                    $dayRecord = $data["records"]->get($day);
                    $this->hoursData[$activityId][$workerId][$day] = $dayRecord
                        ? $dayRecord->hours
                        : null;
                }
            }
        }
    }

    private function processHoursData(): void
    {
        foreach ($this->hoursData as $activityId => $workers) {
            foreach ($workers as $workerId => $days) {
                foreach ($days as $day => $hours) {
                    // Allow saving hours even on vacation days
                    // This allows workers to work during their vacation if needed

                    $date = Carbon::create($this->year, $this->month, $day);

                    if ($hours !== null && $hours !== "") {
                        $this->createOrUpdateRecord(
                            $workerId,
                            $date,
                            $hours,
                            $activityId
                        );
                    } else {
                        $this->deleteRecord($workerId, $date, $activityId);
                    }
                }
            }
        }
    }

    private function deleteWorkerRecords($workerId): void
    {
        $dateRange = $this->getMonthDateRange();

        WorkerRecord::where("worker_id", $workerId)
            ->where("work_place_id", $this->workplace)
            ->whereBetween("date", $dateRange)
            ->delete();
    }

    private function createOrUpdateRecord(
        $workerId,
        $date,
        $hours,
        $activityId = null
    ): void {
        $record = WorkerRecord::firstOrCreate(
            [
                "worker_id" => $workerId,
                "work_place_id" => $this->workplace,
                "date" => $date,
                "work_place_activity_id" => $activityId,
            ],
            [
                "hours" => $hours,
                "status" => WorkerRecord::WORKER_RECORD_WAITING,
                "creator_id" => Auth::id(),
            ]
        );

        if ($record->hours != $hours) {
            $record->old_value = $record->hours;
            $record->hours = $hours;
            $record->save();
        }
    }

    private function deleteRecord($workerId, $date, $activityId = null): void
    {
        $query = WorkerRecord::where([
            "worker_id" => $workerId,
            "work_place_id" => $this->workplace,
            "date" => $date,
        ]);

        if ($activityId !== null) {
            $query->where("work_place_activity_id", $activityId);
        }

        $query->delete();
    }

    private function processVacationDays($vacation, $dateRange): void
    {
        [$startDate, $endDate] = $dateRange;
        $workerId = $vacation->worker_id;
        $vacStart = Carbon::parse($vacation->start_date);
        $vacEnd = Carbon::parse($vacation->end_date);

        if (!isset($this->vacationData[$workerId])) {
            $this->vacationData[$workerId] = [];
        }

        // Use copies to prevent mutating the original dateRange Carbon objects
        $current = max($vacStart, $startDate)->copy();
        $end = min($vacEnd, $endDate)->copy();

        while ($current <= $end) {
            $this->vacationData[$workerId][$current->day] = [
                "type" => $vacation->type,
                "comment" => $vacation->comment,
                "vacation_id" => $vacation->id,
            ];
            $current->addDay();
        }
    }

    private function getMonthDateRange(): array
    {
        $startDate = Carbon::create(
            $this->year,
            $this->month,
            1
        )->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        return [$startDate, $endDate];
    }

    private function calculateAverageHours($records): float
    {
        $count = $records->count();
        return $count > 0 ? round($records->sum("hours") / $count, 2) : 0;
    }

    private function reloadData(): void
    {
        $this->loadData();
        $this->initializeHoursData();
    }

    // Action creators
    private function getBackAction()
    {
        return Actions\Action::make("back_to_selection")
            ->label("Обратно към избор")
            ->icon("heroicon-o-arrow-left")
            ->color("gray")
            ->url("/service/presences");
    }
    private function getPreviousMonthAction()
    {
        return Actions\Action::make("previous_month")
            ->label("Предишен месец")
            ->icon("heroicon-o-chevron-left")
            ->action(fn() => $this->changeMonth(-1));
    }
    private function getCurrentMonthAction()
    {
        return Actions\Action::make("current_month")
            ->label("Текущ месец")
            ->icon("heroicon-o-home")
            ->action(fn() => $this->goToCurrentMonth());
    }
    private function getNextMonthAction()
    {
        return Actions\Action::make("next_month")
            ->label("Следващ месец")
            ->icon("heroicon-o-chevron-right")
            ->action(fn() => $this->changeMonth(1));
    }
    private function getSaveAction()
    {
        return Actions\Action::make("save_hours")
            ->label("Запази часовете")
            ->icon("heroicon-o-check")
            ->color("success")
            ->action("saveHours")
            ->visible(fn() => !$this->isLocked && $this->hasUnsavedChanges);
    }
    private function getLockAction()
    {
        return Actions\Action::make("lock_month")
            ->label("Заключи месеца")
            ->icon("heroicon-o-lock-closed")
            ->color("warning")
            ->action("lockMonth")
            ->visible(
                fn() => !$this->isLocked &&
                    Auth::user()->hasRole([
                        "admin",
                        "super_admin",
                        "manager",
                        "supervisor",
                    ])
            )
            ->requiresConfirmation()
            ->modalHeading("Заключване на месеца")
            ->modalDescription(
                "Сигурни ли сте, че искате да заключите този месец?"
            );
    }
    private function getUnlockAction()
    {
        return Actions\Action::make("unlock_month")
            ->label("Отключи месеца")
            ->icon("heroicon-o-lock-open")
            ->color("danger")
            ->action("unlockMonth")
            ->visible(
                fn() => $this->isLocked &&
                    Auth::user()->hasRole(["admin", "super_admin", "manager"])
            )
            ->requiresConfirmation()
            ->modalHeading("Отключване на месеца")
            ->modalDescription(
                "Сигурни ли сте, че искате да отключите този месец?"
            );
    }
    private function getExportAction()
    {
        return Actions\Action::make("export_monthly_excel")
            ->label("Експорт Excel")
            ->icon("heroicon-o-table-cells")
            ->color("info")
            ->action(fn() => $this->exportMonthlyExcel());
    }
    private function getPrintAction()
    {
        return Actions\Action::make("print_monthly")
            ->label("Печат / PDF")
            ->icon("heroicon-o-printer")
            ->color("success")
            ->url(
                fn() => route("service.presence.print-monthly", [
                    "workplace" => $this->workplace,
                    "year" => $this->year,
                    "month" => $this->month,
                ]),
                shouldOpenInNewTab: true
            )
            ->visible(fn() => $this->isLocked);
    }

    public function getWorkplaceActivitiesUrl(): string
    {
        return WorkPlaceResource::getUrl("activities", [
            "record" => $this->workplace,
        ]);
    }

    public function getManageWorkersUrl(): string
    {
        return sprintf(
            "/service/presences/monthly/%d/%s/workers/add",
            $this->workplace,
            sprintf("%02d-%d", $this->month, $this->year)
        );
    }

    public function getConfigureHoursUrl(): string
    {
        return sprintf(
            "/service/presences/monthly/%d/%s/configure-hours",
            $this->workplace,
            sprintf("%02d-%d", $this->month, $this->year)
        );
    }

    // Notification helpers

    // TODO: Price & Total calculation methods - need old app logic
    private function calculateWorkerPrice($worker, $totalHours): float
    {
        $activityId = $this->getWorkerMonthlyActivityId($worker->id);
        if (!$activityId) {
            return 0.0;
        }

        $activity = WorkPlaceActivity::find($activityId);
        if (!$activity) {
            return 0.0;
        }

        return $this->calculateWorkerPriceForActivity($activity, $totalHours);
    }

    private function calculateWorkerTotal($worker, $totalHours): float
    {
        $activityId = $this->getWorkerMonthlyActivityId($worker->id);
        if (!$activityId) {
            return 0.0;
        }

        $activity = WorkPlaceActivity::find($activityId);
        if (!$activity) {
            return 0.0;
        }

        return $this->calculateWorkerTotalForActivity($activity, $totalHours);
    }

    private function calculateWorkerPriceForActivity(
        WorkPlaceActivity $activity,
        float $totalHours,
        ?float $hourRate = null
    ): float {
        if ($totalHours <= 0) {
            return 0.0;
        }

        $effectiveHourRate =
            $hourRate ??
            $this->getHourCostOnWorkPlaceActivityByDate(
                $activity,
                sprintf("%02d-%d", $this->month, $this->year)
            );

        if ($effectiveHourRate <= 0) {
            return 0.0;
        }

        return round($totalHours * $effectiveHourRate, 2);
    }

    private function calculateWorkerTotalForActivity(
        WorkPlaceActivity $activity,
        float $totalHours
    ): float {
        return round($totalHours, 2);
    }

    private function showUnsavedChangesWarning()
    {
        Notification::make()
            ->title("Незапазени промени")
            ->body(
                "Имате незапазени промени. Моля запазете ги преди да променяте месеца."
            )
            ->warning()
            ->send();
    }
    private function showSuccessNotification($message)
    {
        Notification::make()
            ->title("Успешно")
            ->body($message)
            ->success()
            ->send();
    }
    private function showWarningNotification($message)
    {
        Notification::make()
            ->title("Внимание")
            ->body($message)
            ->warning()
            ->send();
    }
    private function showErrorNotification($message)
    {
        Notification::make()->title("Грешка")->body($message)->danger()->send();
    }

    // Budget checking methods (ported from PresenceController)
    private function prepareHoursDataForBudgetCheck(): array
    {
        $userData = [];

        foreach ($this->hoursData as $activityId => $workers) {
            foreach ($workers as $workerId => $days) {
                foreach ($days as $day => $hours) {
                    if ($hours !== null && $hours !== "" && $hours > 0) {
                        $userData[] = [
                            "workPlaceActivityId" => $activityId,
                            "workerId" => $workerId,
                            "day" => $day,
                            "hours" => (float) $hours,
                        ];
                    }
                }
            }
        }

        return $userData;
    }

    private function getWorkerActivityId($worker): int
    {
        return $this->getWorkerMonthlyActivityId($worker->id) ?? 0;
    }

    private function getWorkerMonthlyActivityId(int $workerId): ?int
    {
        $start = $this->getMonthStartDate();
        $end = $start->copy()->endOfMonth();

        $record = WorkerRecord::where("work_place_id", $this->workplace)
            ->where("worker_id", $workerId)
            ->whereBetween("date", [
                $start->toDateString(),
                $end->toDateString(),
            ])
            ->orderBy("date")
            ->first();

        if ($record && $record->work_place_activity_id) {
            return $record->work_place_activity_id;
        }

        $activity = WorkPlaceActivity::where("work_place_id", $this->workplace)
            ->whereNull("date")
            ->where("copied", WorkPlaceActivity::NOT_COPIED_ACTIVITY)
            ->whereHas("temporaryWorkers", function ($query) use (
                $workerId,
                $start
            ) {
                $query
                    ->where("viki_workers.id", $workerId)
                    ->wherePivot("date", $start->toDateString());
            })
            ->first();

        if ($activity) {
            return $activity->id;
        }

        $worker = Worker::find($workerId);
        if (!$worker || !$worker->work_place_activity_id) {
            return null;
        }

        $baseActivity = WorkPlaceActivity::find(
            $worker->work_place_activity_id
        );
        if (!$baseActivity) {
            return null;
        }

        if (
            $baseActivity->work_place_id === $this->workplace &&
            $baseActivity->date === null &&
            (int) $baseActivity->copied ===
                WorkPlaceActivity::NOT_COPIED_ACTIVITY
        ) {
            return $baseActivity->id;
        }

        $mappedBase = WorkPlaceActivity::query()
            ->where("work_place_id", $this->workplace)
            ->whereNull("date")
            ->where("copied", WorkPlaceActivity::NOT_COPIED_ACTIVITY)
            ->where("activity", $baseActivity->activity)
            ->where("type_working", $baseActivity->type_working)
            ->first();

        return $mappedBase?->id;
    }

    private function getMonthStartDate(): Carbon
    {
        return Carbon::create($this->year, $this->month, 1);
    }

    private function checkIfInBudget($extraData): array
    {
        $dateString = sprintf("%02d-%d", $this->month, $this->year);

        $workPlaceActivities = WorkPlaceActivity::where(
            "work_place_id",
            $this->workplace
        )
            ->whereNull("date")
            ->where("copied", WorkPlaceActivity::NOT_COPIED_ACTIVITY)
            ->get();

        $workPlaceActivityUsedBudget = [];
        $workPlaceActivityCostForHour = [];
        $workPlaceActivityBudgetBeforeChange = [];
        $extraDataNegativeValueKeys = [];

        foreach ($workPlaceActivities as $workPlaceActivity) {
            $workPlaceActivityWorkers = $this->getWorkPlaceActivityWorkersByDate(
                $workPlaceActivity,
                $dateString
            );

            $workPlaceActivityUsedWorkingHours = 0;
            $workPlaceActivityBudgetBeforeChangeHours = 0;

            foreach ($workPlaceActivityWorkers as $workPlaceActivityWorker) {
                foreach (
                    $workPlaceActivityWorker->workerRecords
                    as $workerRecord
                ) {
                    $dataIsCalculated = false;

                    foreach ($extraData as $key => $extraDatum) {
                        if (
                            $extraDatum["workPlaceActivityId"] ==
                                $workPlaceActivity->id &&
                            $extraDatum["workerId"] ==
                                $workerRecord->worker_id &&
                            sprintf(
                                "%04d-%02d-%02d",
                                $this->year,
                                $this->month,
                                $extraDatum["day"]
                            ) == $workerRecord->date
                        ) {
                            $workPlaceActivityUsedWorkingHours +=
                                $extraDatum["hours"];
                            $dataIsCalculated = true;

                            if ($extraDatum["hours"] < $workerRecord->hours) {
                                $extraDataNegativeValueKeys[] = $key;
                            }
                            unset($extraData[$key]);
                        }
                    }
                    $workPlaceActivityBudgetBeforeChangeHours +=
                        $workerRecord->hours;

                    if (!$dataIsCalculated) {
                        $workPlaceActivityUsedWorkingHours +=
                            $workerRecord->hours;
                    }
                }
            }

            foreach ($extraData as $key => $extraDatum) {
                if (
                    $extraDatum["workPlaceActivityId"] == $workPlaceActivity->id
                ) {
                    $workPlaceActivityUsedWorkingHours += $extraDatum["hours"];
                }
            }

            $hourCost = $this->getHourCostOnWorkPlaceActivityByDate(
                $workPlaceActivity,
                $dateString
            );
            $workPlaceActivityCostForHour[$workPlaceActivity->id] = $hourCost;
            $workPlaceActivityUsedBudget[$workPlaceActivity->id] =
                $workPlaceActivityUsedWorkingHours * $hourCost;
            $workPlaceActivityBudgetBeforeChange[$workPlaceActivity->id] =
                $workPlaceActivityBudgetBeforeChangeHours * $hourCost;
        }

        $workPlace = WorkPlace::with([
            "overBudget" => function ($q) use ($dateString) {
                $q->where(
                    "viki_workplace_month_budget.date",
                    sprintf("%04d-%02d-01", $this->year, $this->month)
                );
            },
        ])->find($this->workplace);

        $workPlaceBudget = $workPlace->getBudgetByDate($dateString);

        if ($workPlace->overBudget->count() > 0) {
            $workPlaceBudget =
                $workPlaceBudget + $workPlace->overBudget->first()->sum_up;
        }

        if ($workPlaceBudget < array_sum($workPlaceActivityUsedBudget)) {
            return [
                "inBudget" => false,
                "overBudget" => $this->round_up(
                    array_sum($workPlaceActivityUsedBudget) - $workPlaceBudget,
                    2
                ),
                "budget" => $workPlaceBudget,
                "workPlaceActivityCostForHour" => $workPlaceActivityCostForHour,
                "freeBudgetBeforeChange" =>
                    $workPlaceBudget -
                    array_sum($workPlaceActivityBudgetBeforeChange),
                "dataNegativeValueKeys" => $extraDataNegativeValueKeys,
            ];
        }

        return ["inBudget" => true];
    }

    private function createApproveRequest($overBudget): int
    {
        $approveRequest = new \viki\Service\Models\Elequent\Approvement();
        $approveRequest->work_place_id = $this->workplace;
        $approveRequest->date = sprintf(
            "%04d-%02d-01",
            $this->year,
            $this->month
        );
        $approveRequest->creator_id = Auth::user()->id;
        $approveRequest->status =
            \viki\Service\Models\Elequent\Approvement::STATUS_NEW;
        $approveRequest->type_id =
            \viki\Service\Models\Elequent\Approvement::TYPE_APPR_OBJECT;
        $approveRequest->sum_above_budget = $overBudget;

        $approveRequest->save();

        // Send notification emails to managers
        $workPlace = WorkPlace::find($this->workplace);
        $regions = $workPlace->region()->get();

        foreach ($regions as $region) {
            $managers = $region->managers()->get();

            foreach ($managers as $manager) {
                $mail = \Illuminate\Support\Facades\Mail::to($manager->email);
                $mail->send(
                    new \viki\Service\Mail\VikiRequestAction([
                        "reason" => "повишаване на бюджета",
                        "workerplace" => $workPlace->name,
                        "userWhoTriggerChange" => Auth::user()->name,
                        "link" => route("service.approvement"),
                    ])
                );
            }
        }

        return $approveRequest->id;
    }

    private function processHoursDataWithApproval(
        $processedData,
        $budgetCheck,
        $approvalId
    ): void {
        $remainingFreeBudget = $budgetCheck["freeBudgetBeforeChange"];

        foreach ($this->hoursData as $activityId => $workers) {
            foreach ($workers as $workerId => $days) {
                foreach ($days as $day => $hours) {
                    // Allow saving hours even on vacation days
                    // This allows workers to work during their vacation if needed

                    $date = Carbon::create($this->year, $this->month, $day);

                    if ($hours !== null && $hours !== "") {
                        // Determine if this record should be approved or waiting
                        $status = $this->determineRecordStatus(
                            $activityId,
                            $workerId,
                            $day,
                            $hours,
                            $budgetCheck,
                            $remainingFreeBudget
                        );
                        $this->createOrUpdateRecordWithStatus(
                            $workerId,
                            $date,
                            $hours,
                            $status,
                            $approvalId,
                            $activityId
                        );
                    } else {
                        $this->deleteRecord($workerId, $date, $activityId);
                    }
                }
            }
        }
    }

    private function determineRecordStatus(
        $activityId,
        $workerId,
        $day,
        $hours,
        $budgetCheck,
        &$remainingFreeBudget
    ): int {
        // Use the provided activityId instead of looking it up
        if (!$activityId) {
            return WorkerRecord::WORKER_RECORD_WAITING;
        }

        $hourCost =
            $budgetCheck["workPlaceActivityCostForHour"][$activityId] ?? 15.0;
        $recordCost = $hours * $hourCost;

        // If we have enough free budget, approve and subtract from remaining
        if ($remainingFreeBudget >= $recordCost) {
            $remainingFreeBudget -= $recordCost;
            return WorkerRecord::WORKER_RECORD_APPROVED;
        }

        // Not enough budget - set as waiting for approval
        return WorkerRecord::WORKER_RECORD_WAITING;
    }

    private function createOrUpdateRecordWithStatus(
        $workerId,
        $date,
        $hours,
        $status,
        $approvalId = null,
        $activityId = null
    ): void {
        $workerRecordData = [
            "hours" => $hours,
            "day_count" => 0,
            "status" => $status,
            "start_date" => date("Y-m-d"),
            "end_date" => date("Y-m-d"),
            "creator_id" => Auth::id(),
        ];

        if ($status !== WorkerRecord::WORKER_RECORD_WAITING) {
            $workerRecordData["old_value"] = $hours;
        }

        if ($approvalId && $status === WorkerRecord::WORKER_RECORD_WAITING) {
            $workerRecordData["approvement_id"] = $approvalId;
        }

        $uniqueKeys = [
            "worker_id" => $workerId,
            "work_place_id" => $this->workplace,
            "date" => $date,
        ];

        if ($activityId !== null) {
            $uniqueKeys["work_place_activity_id"] = $activityId;
        }

        WorkerRecord::updateOrCreate($uniqueKeys, $workerRecordData);
    }

    // Helper methods ported from PresenceController
    private function getHourCostOnWorkPlaceActivityByDate(
        $workPlaceActivity,
        $date,
        ?float $overrideSalary = null,
        ?float $overrideHoursPerDay = null
    ): float {
        $workPlaceActivityWorkingHours = $this->getActivityWorkingHoursForDate(
            $workPlaceActivity,
            $date,
            $overrideHoursPerDay
        );

        if ($workPlaceActivityWorkingHours === 0) {
            return 0;
        }

        $salary = $overrideSalary ?? $workPlaceActivity->neto_salary;

        return $salary / $workPlaceActivityWorkingHours;
    }

    private function getActivityWorkingHoursForDate(
        $workPlaceActivity,
        $date,
        ?float $overrideHoursPerDay = null
    ): float {
        $workPlaceActivityHours = $workPlaceActivity
            ->hours()
            ->where("date", sprintf("%04d-%02d-01", $this->year, $this->month))
            ->first();

        $hoursPerDay = $overrideHoursPerDay
            ? (int) $overrideHoursPerDay
            : (int) WorkPlaceActivityHoursPerDay::findHoursPerDayPerActivity(
                $workPlaceActivity->id
            );
        if (
            $hoursPerDay <= 0 &&
            preg_match(
                "/(\d+)\s*ч/u",
                (string) $workPlaceActivity->activity,
                $matches
            )
        ) {
            $hoursPerDay = (int) ($matches[1] ?? 0);
        }
        if ($hoursPerDay <= 0) {
            $hoursPerDay = 8;
        }

        // Calculate working hours based on working days and activity hours-per-day.
        $calculatedHours =
            (cal_days_in_month(CAL_GREGORIAN, $this->month, $this->year) -
                count($this->getAllNonWorkingDays($this->month, $this->year))) *
            $hoursPerDay;

        if ($workPlaceActivityHours) {
            // For WORKING_BY_HOURS (Сумарно) workers, always use configured hours
            // These workers may legitimately work very few hours (e.g., 1 hour/month for weekend-only workers)
            if (
                $workPlaceActivity->type_working ==
                WorkPlaceActivity::WORKING_BY_HOURS
            ) {
                return $workPlaceActivityHours->hours_for_person;
            }
        }

        // WORKING_STANDART always uses calculated monthly hours from activity hours/day.
        if (
            $workPlaceActivity->type_working ==
            WorkPlaceActivity::WORKING_STANDART
        ) {
            return $calculatedHours;
        }

        // Fallback for WORKING_BY_HOURS (Сумарно) activities without valid hours record
        // Use a default calculation to avoid division by zero
        return $calculatedHours;
    }

    private function getWorkPlaceActivityWorkersByDate(
        $workPlaceActivity,
        $date
    ) {
        $temporaryWorkers = $workPlaceActivity
            ->temporaryWorkers()
            ->with([
                "workerRecords" => function ($q) use (
                    $workPlaceActivity,
                    $date
                ) {
                    $q->where(
                        "viki_worker_records.work_place_activity_id",
                        "=",
                        $workPlaceActivity->id
                    );
                    $q->where(
                        "date",
                        "like",
                        sprintf("%04d-%02d-%%", $this->year, $this->month)
                    );
                },
            ])
            ->wherePivot(
                "date",
                sprintf("%04d-%02d-01", $this->year, $this->month)
            )
            ->get();

        return Worker::whereHas("workPlaceActivity", function ($q) use (
            $workPlaceActivity
        ) {
            $q->where("id", "=", $workPlaceActivity->id);
        })
            ->with([
                "workerRecords" => function ($q) use (
                    $workPlaceActivity,
                    $date
                ) {
                    $q->where(
                        "viki_worker_records.work_place_activity_id",
                        "=",
                        $workPlaceActivity->id
                    );
                    $q->where(
                        "date",
                        "like",
                        sprintf("%04d-%02d-%%", $this->year, $this->month)
                    );
                },
            ])
            ->get()
            ->merge($temporaryWorkers);
    }

    private function getAllNonWorkingDays($month, $year): array
    {
        $specialDays = $this->getSpecialDays($month, $year);
        $weekDays = $this->getWeekDays($month, $year);

        if ($specialDays) {
            foreach ($specialDays as $specialDay) {
                if (!in_array($specialDay, $weekDays)) {
                    $weekDays[] = $specialDay;
                }
            }
        }

        return $weekDays;
    }

    public function getSpecialDayInfo(int $day): ?array
    {
        $map = $this->getSpecialDaysMap();

        return $map[$day] ?? null;
    }

    /**
     * @return array<int, array{label: string, type: int}>
     */
    private function getSpecialDaysMap(): array
    {
        if ($this->cachedSpecialDays !== null) {
            return $this->cachedSpecialDays;
        }

        $specialDays = SpecialDay::where(
            "date",
            "like",
            sprintf("%04d-%02d-%%", $this->year, $this->month)
        )->get();

        $map = [];

        foreach ($specialDays as $specialDay) {
            $day = (int) \Carbon\Carbon::parse($specialDay->date)->day;
            $map[$day] = [
                "label" => $specialDay->comment ?? "Празничен ден",
                "type" => (int) $specialDay->type,
            ];
        }

        $this->cachedSpecialDays = $map;

        return $this->cachedSpecialDays;
    }

    private function getSpecialDays($month, $year): array
    {
        $specialDays = \viki\Service\Models\Elequent\SpecialDay::where(
            "date",
            "like",
            sprintf("%04d-%02d-%%", $year, $month)
        )->get();

        $specialDaysArr = [];
        foreach ($specialDays as $specialDay) {
            $specialDaysArr[] = (int) substr(
                $specialDay->date,
                strrpos($specialDay->date, "-") + 1
            );
        }

        return $specialDaysArr;
    }

    private function getWeekDays($month, $year): array
    {
        $weekDays = [];
        foreach (
            range(1, cal_days_in_month(CAL_GREGORIAN, $month, $year))
            as $day
        ) {
            if (date("N", strtotime($day . "-" . $month . "-" . $year)) >= 6) {
                $weekDays[] = $day;
            }
        }
        return $weekDays;
    }

    private function round_up($value, $precision): float
    {
        $pow = pow(10, $precision);
        return (ceil($pow * $value) +
            ceil($pow * $value - ceil($pow * $value))) /
            $pow;
    }
}
