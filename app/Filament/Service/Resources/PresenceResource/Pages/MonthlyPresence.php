<?php

namespace App\Filament\Service\Resources\PresenceResource\Pages;

use App\Filament\Service\Resources\PresenceResource;
use Filament\Resources\Pages\Page;
use Filament\Actions;
use Filament\Notifications\Notification;
use Viki\Service\Models\Elequent\WorkPlace;
use Viki\Service\Models\Elequent\Worker;
use Viki\Service\Models\Elequent\WorkerRecord;
use Viki\Service\Models\Elequent\WorkPlaceActivity;
use Viki\Service\Models\Elequent\Vacation;
use Viki\Service\Models\Elequent\VikiUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use viki\Service\Models\Elequent\Approvement;
use viki\Service\Models\Elequent\SpecialDay;

class MonthlyPresence extends Page
{
    protected static string $resource = PresenceResource::class;
    protected static string $view = 'filament.service.resources.presence-resource.pages.monthly-presence';

    // Route parameters
    public int $workplace;
    public ?int $year = null;
    public ?int $month = null;
    
    // Data properties
    public $workplaces;
    public $monthlyData;
    public $workplaceData;
    public $activities;
    public $vacationData = [];
    public $availableWorkers = [];
    
    // State properties
    public $isLocked = false;
    public $showWorkerModal = false;
    public $selectedWorkers = [];
    public $hoursData = [];
    public $hasUnsavedChanges = false;

    public function mount(int $workplace, ?string $date = null): void
    {
        $this->workplace = $workplace;
        $this->parseDateParameter($date);
        $this->loadData();
        $this->initializeHoursData();
        
        // Check if we should open the worker modal (from replacement worker route)
        if (session('open_worker_modal')) {
            $this->showWorkerModal = true;
            session()->forget('open_worker_modal');
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getBackAction(),
            $this->getPreviousMonthAction(),
            $this->getCurrentMonthAction(),
            $this->getNextMonthAction(),
            $this->getSaveAction(),
            $this->getLockAction(),
            $this->getUnlockAction(),
            $this->getExportAction(),
            $this->getManageWorkersAction(),
        ];
    }

    // Navigation methods
    public function changeMonth(int $months): void
    {
        if ($this->hasUnsavedChanges) {
            $this->showUnsavedChangesWarning();
            return;
        }

        $date = Carbon::create($this->year, $this->month, 1)->addMonths($months);
        $dateString = sprintf('%02d-%d', $date->month, $date->year);
        $this->redirect("/service/presences/monthly/{$this->workplace}/{$dateString}");
    }

    public function goToCurrentMonth(): void
    {
        if ($this->hasUnsavedChanges) {
            $this->showUnsavedChangesWarning();
            return;
        }

        $now = Carbon::now();
        $dateString = sprintf('%02d-%d', $now->month, $now->year);
        $this->redirect("/service/presences/monthly/{$this->workplace}/{$dateString}");
    }

    // Hours management
    public function updatedHoursData(): void
    {
        $this->hasUnsavedChanges = true;
    }

    public function saveHours(): void
    {
        try {
            DB::beginTransaction();
            
            // Process the hours data and prepare for budget checking
            $processedData = $this->prepareHoursDataForBudgetCheck();
            
            // Check if the changes exceed budget
            $budgetCheck = $this->checkIfInBudget($processedData);
            
            if ($budgetCheck['inBudget'] === true) {
                // Within budget - save all records as approved
                $this->processHoursData();
                
            } else {
                // Budget exceeded - create approval request
                $approvalId = $this->createApproveRequest($budgetCheck['overBudget']);
                
                // Save records with proper approval handling
                $this->processHoursDataWithApproval($processedData, $budgetCheck, $approvalId);
            }
            
            DB::commit();
            
            $this->hasUnsavedChanges = false;
            $this->reloadData();
            
            if ($budgetCheck['inBudget'] === false) {
                $this->showWarningNotification("Часовете са запазени, но надвишават бюджета с {$budgetCheck['overBudget']} лв. Създадено е искане за одобрение. Можете да го видите в секция 'Одобрения'.");
            } else {
                $this->showSuccessNotification('Часовете са запазени успешно в рамките на бюджета.');
            }

        } catch (\Exception $e) {
            DB::rollBack();
            $this->showErrorNotification('Грешка при запазване: ' . $e->getMessage());
        }
    }

    // Worker management
    public function openWorkerManagement(): void
    {
        $this->loadAvailableWorkers();
        $this->selectedWorkers = [];
        $this->showWorkerModal = true;
    }

    public function closeWorkerModal(): void
    {
        $this->showWorkerModal = false;
        $this->selectedWorkers = [];
    }

    public function addSelectedWorkers(): void
    {
        if (empty($this->selectedWorkers)) {
            $this->showWarningNotification('Моля изберете поне един работник за добавяне.');
            return;
        }

        try {
            DB::beginTransaction();
            $this->createWorkerRecords();
            DB::commit();
            
            $this->reloadData();
            $this->closeWorkerModal();
            $this->showSuccessNotification('Работниците са добавени успешно.');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->showErrorNotification('Грешка при добавяне: ' . $e->getMessage());
        }
    }

    public function removeWorkerFromMonth($workerId): void
    {
        try {
            DB::beginTransaction();
            $this->deleteWorkerRecords($workerId);
            DB::commit();
            
            $this->reloadData();
            $this->showSuccessNotification('Работникът е премахнат успешно.');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->showErrorNotification('Грешка при премахване: ' . $e->getMessage());
        }
    }

    // Month locking
    public function lockMonth(): void
    {
        $this->isLocked = true;
        $this->showSuccessNotification('Месецът е заключен успешно.');
    }

    public function unlockMonth(): void
    {
        $this->isLocked = false;
        $this->showSuccessNotification('Месецът е отключен успешно.');
    }

    // Export
    public function exportMonthlyExcel(): void
    {
        if (!$this->monthlyData || $this->monthlyData->isEmpty()) {
            $this->showErrorNotification('Няма данни за експорт');
            return;
        }

        $params = ['workplace' => $this->workplace, 'year' => $this->year, 'month' => $this->month];
        $url = route('service.presence.export-monthly', $params);
        $this->js("window.open('$url', '_blank')");
        $this->showSuccessNotification('Excel файлът се генерира в нов прозорец...');
    }

    // Vacation methods
    public function getVacationTypeInfo($type): array
    {
        $types = [
            Vacation::PAYD_VACATION => [
                'label' => 'Платена отпуска', 'short' => 'ПО', 'color' => 'green',
                'style' => 'background-color: #dcfce7; border-color: #16a34a; color: #166534;'
            ],
            Vacation::NOT_PAYD_VACATION => [
                'label' => 'Неплатена отпуска', 'short' => 'НО', 'color' => 'blue',
                'style' => 'background-color: #dbeafe; border-color: #2563eb; color: #1d4ed8;'
            ],
            Vacation::HOSPITAL_SHEET => [
                'label' => 'Болничен', 'short' => 'БЛ', 'color' => 'red',
                'style' => 'background-color: #fee2e2; border-color: #dc2626; color: #991b1b;'
            ]
        ];

        return $types[$type] ?? [
            'label' => 'Неизвестен тип', 'short' => '?', 'color' => 'gray',
            'style' => 'background-color: #f3f4f6; border-color: #6b7280; color: #374151;'
        ];
    }
    
    public function getVacationTypesLegend(): array
    {
        return [
            Vacation::PAYD_VACATION => $this->getVacationTypeInfo(Vacation::PAYD_VACATION),
            Vacation::NOT_PAYD_VACATION => $this->getVacationTypeInfo(Vacation::NOT_PAYD_VACATION),
            Vacation::HOSPITAL_SHEET => $this->getVacationTypeInfo(Vacation::HOSPITAL_SHEET),
        ];
    }

    // Utility methods
    public function getTitle(): string
    {
        $workplaceName = $this->workplaces[$this->workplace] ?? 'Неизвестно място';
        return "Месечно управление - {$workplaceName} - {$this->getMonthName()}";
    }

    public function getMonthName(): string
    {
        $months = [
            1 => 'Януари', 2 => 'Февруари', 3 => 'Март', 4 => 'Април',
            5 => 'Май', 6 => 'Юни', 7 => 'Юли', 8 => 'Август',
            9 => 'Септември', 10 => 'Октомври', 11 => 'Ноември', 12 => 'Декември'
        ];
        
        return $months[$this->month] . ' ' . $this->year;
    }

    public function getDaysInMonth(): int
    {
        return Carbon::create($this->year, $this->month, 1)->daysInMonth;
    }

    // Private helper methods
    private function parseDateParameter(?string $date): void
    {
        if ($date && count($dateParts = explode('-', $date)) === 2) {
            $this->month = (int)$dateParts[0];
            $this->year = (int)$dateParts[1];
        } else {
            $now = Carbon::now();
            $this->year = $now->year;
            $this->month = $now->month;
        }
    }

    private function loadData(): void
    {
        $this->loadUserWorkplaces();
        
        if (!$this->workplace || !$this->workplaces->has($this->workplace)) {
            if ($this->workplace) abort(403, 'Нямате достъп до този обект');
            return;
        }

        $this->workplaceData = WorkPlace::with('region', 'client')->find($this->workplace);
        $this->loadActivities();
        $this->loadVacationData();
        $this->loadAvailableWorkers();
        $this->loadMonthlyData();
    }

    private function loadUserWorkplaces(): void
    {
        $user = VikiUser::find(Auth::id());
        
        $workplaces = match(true) {
            Auth::user()->hasRole(['admin', 'super_admin']) => 
                WorkPlace::where('status', WorkPlace::WORK_PLACE_ACTIVE)->with('region')->orderBy('name')->get(),
            Auth::user()->hasRole('manager') => 
                WorkPlace::where('status', WorkPlace::WORK_PLACE_ACTIVE)
                    ->whereIn('region_id', VikiUser::getCurrentUserRegionId(Auth::id()))
                    ->with('region')->orderBy('name')->get(),
            Auth::user()->hasRole('supervisor') => 
                $user->activeWorkPlaces()->with('region')->orderBy('name')->get(),
            default => collect()
        };

        $this->workplaces = $workplaces->pluck('name', 'id');
    }

    private function loadMonthlyData(): void
    {
        $dateRange = $this->getMonthDateRange();
        
        $workerIdsWithRecords = WorkerRecord::where('work_place_id', $this->workplace)
            ->whereBetween('date', $dateRange)
            ->distinct()
            ->pluck('worker_id');

        // Get workers with their WorkPlaceActivity relationships
        $workersQuery = Worker::whereIn('id', $workerIdsWithRecords)
            ->where('status', Worker::WORKER_ACTIVE)
            ->with('workPlaceActivity');
        
        // Order by position if column exists (for development/future compatibility)
        if (Schema::hasColumn('viki_workers', 'position')) {
            $workersQuery->orderBy('position');
        }
        
        $workers = $workersQuery
            ->orderBy('name')
            ->orderBy('family_name')
            ->get();

        $records = WorkerRecord::where('work_place_id', $this->workplace)
            ->whereBetween('date', $dateRange)
            ->with(['worker', 'activity'])
            ->get()
            ->groupBy('worker_id');

        // Group workers by WorkPlaceActivity - using arrays, not collections
        $groupedByActivity = [];
        
        foreach ($workers as $worker) {
            $workerRecords = $records->get($worker->id, collect());
            
            // Get the worker's activity for this month/workplace
            $activity = $worker->workPlaceActivity;
            
            // If no specific activity found, try to get from worker records
            if (!$activity && $workerRecords->isNotEmpty()) {
                $activity = $workerRecords->first()->activity;
            }
            
            // Use position as fallback if no activity found
            $activityKey = $activity ? $activity->id : 'position_' . $worker->position;
            $activityName = $activity ? $activity->activity : $worker->position;
            
            // Initialize activity group if not exists - using arrays
            if (!isset($groupedByActivity[$activityKey])) {
                $groupedByActivity[$activityKey] = [
                    'activity' => $activity,
                    'activity_name' => $activityName,
                    'activity_salary' => $activity ? ($activity->neto_salary + $activity->social_plus) : 0,
                    'workers' => [], // Use array instead of collection
                    'group_totals' => [
                        'total_price' => 0,
                        'total_hours' => 0,
                        'total_calculated' => 0
                    ]
                ];
            }
            
            // Calculate worker data
            $totalHours = $workerRecords->sum('hours');
            $calculatedPrice = $this->calculateWorkerPrice($worker, $totalHours);
            $calculatedTotal = $this->calculateWorkerTotal($worker, $totalHours);
            
            $workerData = [
                'worker' => $worker,
                'total_hours' => $totalHours,
                'working_days' => $workerRecords->count(),
                'average_hours' => $this->calculateAverageHours($workerRecords),
                'records' => $workerRecords->keyBy(fn($record) => Carbon::parse($record->date)->day),
                'calculated_price' => $calculatedPrice,
                'calculated_total' => $calculatedTotal,
            ];
            
            // Add worker to activity group - array push
            $groupedByActivity[$activityKey]['workers'][] = $workerData;
            
            // Update group totals
            $groupedByActivity[$activityKey]['group_totals']['total_price'] += $calculatedPrice;
            $groupedByActivity[$activityKey]['group_totals']['total_hours'] += $totalHours;
            $groupedByActivity[$activityKey]['group_totals']['total_calculated'] += $calculatedTotal;
        }

        // Convert back to collection for consistency with rest of the app
        $this->monthlyData = collect($groupedByActivity);
    }



    private function loadActivities(): void
    {
        $this->activities = WorkPlaceActivity::where('work_place_id', $this->workplace)
            ->where('date', 'like', sprintf('%02d-%d%%', $this->month, $this->year))
            ->get();
    }

    private function loadVacationData(): void
    {
        if (!$this->workplace) return;

        $dateRange = $this->getMonthDateRange();
        
        $vacations = Vacation::whereHas('worker', function ($query) {
                $query->where('work_place_id', $this->workplace)->where('status', Worker::WORKER_ACTIVE);
            })
            ->where('status', 1)
            ->where(function ($query) use ($dateRange) {
                [$startDate, $endDate] = $dateRange;
                $query->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('start_date', [$startDate, $endDate])
                      ->orWhereBetween('end_date', [$startDate, $endDate])
                      ->orWhere(function ($q2) use ($startDate, $endDate) {
                          $q2->where('start_date', '<=', $startDate)->where('end_date', '>=', $endDate);
                      });
                });
            })
            ->with('worker')
            ->get();

        $this->vacationData = [];
        
        foreach ($vacations as $vacation) {
            $this->processVacationDays($vacation, $dateRange);
        }
    }

    private function loadAvailableWorkers(): void
    {
        if (!$this->workplace) {
            $this->availableWorkers = [];
            return;
        }

        $dateRange = $this->getMonthDateRange();
        $existingWorkerIds = WorkerRecord::where('work_place_id', $this->workplace)
            ->whereBetween('date', $dateRange)
            ->distinct()
            ->pluck('worker_id');

        $this->availableWorkers = Worker::where('work_place_id', $this->workplace)
            ->where('status', Worker::WORKER_ACTIVE)
            ->whereNotIn('id', $existingWorkerIds)
            ->orderBy('name')
            ->get()
            ->map(fn($worker) => [
                'id' => $worker->id,
                'name' => $worker->name . ' ' . $worker->family_name,
                'egn' => $worker->egn,
                'position' => $worker->position,
            ])
            ->toArray();
    }

    private function initializeHoursData(): void
    {
        $this->hoursData = [];
        
        if (!$this->monthlyData) return;

        foreach ($this->monthlyData as $activityGroup) {
            // Access workers as array since we're using arrays inside collections
            foreach ($activityGroup['workers'] as $data) {
                $workerId = $data['worker']->id;
                $this->hoursData[$workerId] = [];
                
                for ($day = 1; $day <= $this->getDaysInMonth(); $day++) {
                    if (isset($this->vacationData[$workerId][$day])) continue;
                    
                    $dayRecord = $data['records']->get($day);
                    $this->hoursData[$workerId][$day] = $dayRecord ? $dayRecord->hours : null;
                }
            }
        }
    }

    private function processHoursData(): void
    {
        foreach ($this->hoursData as $workerId => $days) {
            foreach ($days as $day => $hours) {
                if (isset($this->vacationData[$workerId][$day])) continue;
                
                $date = Carbon::create($this->year, $this->month, $day);
                
                if ($hours !== null && $hours !== '') {
                    $this->createOrUpdateRecord($workerId, $date, $hours);
                } else {
                    $this->deleteRecord($workerId, $date);
                }
            }
        }
    }

    private function createWorkerRecords(): void
    {
        $firstDay = Carbon::create($this->year, $this->month, 1);
        
        foreach ($this->selectedWorkers as $workerId) {
            WorkerRecord::firstOrCreate(
                ['worker_id' => $workerId, 'work_place_id' => $this->workplace, 'date' => $firstDay],
                ['hours' => 0, 'status' => WorkerRecord::WORKER_RECORD_WAITING, 'creator_id' => Auth::id()]
            );
        }
    }

    private function deleteWorkerRecords($workerId): void
    {
        $dateRange = $this->getMonthDateRange();
        
        WorkerRecord::where('worker_id', $workerId)
            ->where('work_place_id', $this->workplace)
            ->whereBetween('date', $dateRange)
            ->delete();
    }

    private function createOrUpdateRecord($workerId, $date, $hours): void
    {
        $record = WorkerRecord::firstOrCreate(
            ['worker_id' => $workerId, 'work_place_id' => $this->workplace, 'date' => $date],
            ['hours' => $hours, 'status' => WorkerRecord::WORKER_RECORD_WAITING, 'creator_id' => Auth::id()]
        );

        if ($record->hours != $hours) {
            $record->old_value = $record->hours;
            $record->hours = $hours;
            $record->save();
        }
    }

    private function deleteRecord($workerId, $date): void
    {
        WorkerRecord::where(['worker_id' => $workerId, 'work_place_id' => $this->workplace, 'date' => $date])->delete();
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
        
        $current = max($vacStart, $startDate);
        $end = min($vacEnd, $endDate);
        
        while ($current <= $end) {
            $this->vacationData[$workerId][$current->day] = [
                'type' => $vacation->type,
                'comment' => $vacation->comment,
                'vacation_id' => $vacation->id
            ];
            $current->addDay();
        }
    }

    private function getMonthDateRange(): array
    {
        $startDate = Carbon::create($this->year, $this->month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        return [$startDate, $endDate];
    }

    private function calculateAverageHours($records): float
    {
        $count = $records->count();
        return $count > 0 ? round($records->sum('hours') / $count, 2) : 0;
    }



    private function reloadData(): void
    {
        $this->loadData();
        $this->initializeHoursData();
    }

    // Action creators
    private function getBackAction() { return Actions\Action::make('back_to_selection')->label('Обратно към избор')->icon('heroicon-o-arrow-left')->color('gray')->url('/service/presences'); }
    private function getPreviousMonthAction() { return Actions\Action::make('previous_month')->label('Предишен месец')->icon('heroicon-o-chevron-left')->action(fn () => $this->changeMonth(-1)); }
    private function getCurrentMonthAction() { return Actions\Action::make('current_month')->label('Текущ месец')->icon('heroicon-o-home')->action(fn () => $this->goToCurrentMonth()); }
    private function getNextMonthAction() { return Actions\Action::make('next_month')->label('Следващ месец')->icon('heroicon-o-chevron-right')->action(fn () => $this->changeMonth(1)); }
    private function getSaveAction() { return Actions\Action::make('save_hours')->label('Запази часовете')->icon('heroicon-o-check')->color('success')->action('saveHours')->visible(fn () => !$this->isLocked && $this->hasUnsavedChanges); }
    private function getLockAction() { return Actions\Action::make('lock_month')->label('Заключи месеца')->icon('heroicon-o-lock-closed')->color('warning')->action('lockMonth')->visible(fn () => !$this->isLocked && Auth::user()->hasRole(['admin', 'super_admin', 'manager']))->requiresConfirmation()->modalHeading('Заключване на месеца')->modalDescription('Сигурни ли сте, че искате да заключите този месец?'); }
    private function getUnlockAction() { return Actions\Action::make('unlock_month')->label('Отключи месеца')->icon('heroicon-o-lock-open')->color('danger')->action('unlockMonth')->visible(fn () => $this->isLocked && Auth::user()->hasRole(['admin', 'super_admin']))->requiresConfirmation()->modalHeading('Отключване на месеца')->modalDescription('Сигурни ли сте, че искате да отключите този месец?'); }
    private function getExportAction() { return Actions\Action::make('export_monthly_excel')->label('Експорт Excel')->icon('heroicon-o-table-cells')->color('info')->action(fn () => $this->exportMonthlyExcel()); }
    private function getManageWorkersAction() { return Actions\Action::make('manage_workers')->label('Управление работници')->icon('heroicon-o-users')->color('warning')->action('openWorkerManagement'); }

    // Notification helpers

    // TODO: Price & Total calculation methods - need old app logic
    private function calculateWorkerPrice($worker, $totalHours): float
    {
        // MISSING INFORMATION: Need old app code to understand how Цена is calculated
        // Possible scenarios:
        // - hourly_rate * total_hours
        // - worker->neto_salary / working_days_in_month * working_days
        // - Some combination with WorkPlaceActivity budget allocation
        
        // Placeholder calculation - replace with actual logic from old app
        return $totalHours * 15.0; // Using fixed rate as placeholder
    }

    private function calculateWorkerTotal($worker, $totalHours): float
    {
        // MISSING INFORMATION: Need old app code to understand how Общо is calculated
        // Questions:
        // - Is Общо different from Цена?
        // - Does it include bonuses, taxes, or other factors?
        // - How does it relate to WorkPlaceActivity budget?
        
        // Placeholder calculation - replace with actual logic from old app
        return $this->calculateWorkerPrice($worker, $totalHours); // Same as price for now
    }

    private function showUnsavedChangesWarning() { Notification::make()->title('Незапазени промени')->body('Имате незапазени промени. Моля запазете ги преди да променяте месеца.')->warning()->send(); }
    private function showSuccessNotification($message) { Notification::make()->title('Успешно')->body($message)->success()->send(); }
    private function showWarningNotification($message) { Notification::make()->title('Внимание')->body($message)->warning()->send(); }
    private function showErrorNotification($message) { Notification::make()->title('Грешка')->body($message)->danger()->send(); }

    // Budget checking methods (ported from PresenceController)
    private function prepareHoursDataForBudgetCheck(): array
    {
        $userData = [];
        
        foreach ($this->hoursData as $workerId => $days) {
            foreach ($days as $day => $hours) {
                if ($hours !== null && $hours !== '' && $hours > 0) {
                    // Find the worker's activity for this workplace
                    $worker = Worker::find($workerId);
                    $workPlaceActivityId = $this->getWorkerActivityId($worker);
                    
                    $userData[] = [
                        'workPlaceActivityId' => $workPlaceActivityId,
                        'workerId' => $workerId,
                        'day' => $day,
                        'hours' => (float)$hours
                    ];
                }
            }
        }
        
        return $userData;
    }
    
    private function getWorkerActivityId($worker): int
    {
        // Try to find the worker's current activity for this workplace
        $activity = WorkPlaceActivity::where('work_place_id', $this->workplace)
            ->where('date', sprintf('%04d-%02d-01', $this->year, $this->month))
            ->first();
            
        if (!$activity) {
            // Create a default activity if none exists using the correct method signature
            $activity = WorkPlaceActivity::create([
                'activity' => $worker->position ?: 'Стандартна дейност',
                'neto_salary' => 800, // Default salary
                'social_plus' => 200, // Default social
                'worker_count' => 1,
                'type_working' => WorkPlaceActivity::WORKING_STANDART,
            ], $this->workplace, sprintf('%04d-%02d-01', $this->year, $this->month));
        }
        
        return $activity->id;
    }

    private function checkIfInBudget($extraData): array
    {
        $dateString = sprintf('%02d-%d', $this->month, $this->year);
        
        $workPlaceActivities = WorkPlaceActivity::where('work_place_id', $this->workplace)
            ->where('date', sprintf('%04d-%02d-01', $this->year, $this->month))
            ->get();

        $workPlaceActivityUsedBudget = [];
        $workPlaceActivityCostForHour = [];
        $workPlaceActivityBudgetBeforeChange = [];
        $extraDataNegativeValueKeys = [];

        foreach ($workPlaceActivities as $workPlaceActivity) {
            $workPlaceActivityWorkers = $this->getWorkPlaceActivityWorkersByDate($workPlaceActivity, $dateString);

            $workPlaceActivityUsedWorkingHours = 0;
            $workPlaceActivityBudgetBeforeChangeHours = 0;

            foreach ($workPlaceActivityWorkers as $workPlaceActivityWorker) {
                foreach ($workPlaceActivityWorker->workerRecords as $workerRecord) {
                    $dataIsCalculated = false;

                    foreach ($extraData as $key => $extraDatum) {
                        if ($extraDatum['workPlaceActivityId'] == $workPlaceActivity->id
                            && $extraDatum['workerId'] == $workerRecord->worker_id
                            && sprintf('%04d-%02d-%02d', $this->year, $this->month, $extraDatum['day']) == $workerRecord->date
                        ) {
                            $workPlaceActivityUsedWorkingHours += $extraDatum['hours'];
                            $dataIsCalculated = true;

                            if ($extraDatum['hours'] < $workerRecord->hours) {
                                $extraDataNegativeValueKeys[] = $key;
                            }
                            unset($extraData[$key]);
                        }
                    }
                    $workPlaceActivityBudgetBeforeChangeHours += $workerRecord->hours;

                    if (!$dataIsCalculated) {
                        $workPlaceActivityUsedWorkingHours += $workerRecord->hours;
                    }
                }
            }

            foreach ($extraData as $key => $extraDatum) {
                if ($extraDatum['workPlaceActivityId'] == $workPlaceActivity->id) {
                    $workPlaceActivityUsedWorkingHours += $extraDatum['hours'];
                }
            }

            $hourCost = $this->getHourCostOnWorkPlaceActivityByDate($workPlaceActivity, $dateString);
            $workPlaceActivityCostForHour[$workPlaceActivity->id] = $hourCost;
            $workPlaceActivityUsedBudget[$workPlaceActivity->id] = $workPlaceActivityUsedWorkingHours * $hourCost;
            $workPlaceActivityBudgetBeforeChange[$workPlaceActivity->id] = $workPlaceActivityBudgetBeforeChangeHours * $hourCost;
        }

        $workPlace = WorkPlace::with(['overBudget' => function($q) use($dateString) {
            $q->where('viki_workplace_month_budget.date', sprintf('%04d-%02d-01', $this->year, $this->month));
        }])->find($this->workplace);

        $workPlaceBudget = $workPlace->getBudgetByDate($dateString);

        if ($workPlace->overBudget->count() > 0) {
            $workPlaceBudget = $workPlaceBudget + $workPlace->overBudget->first()->sum_up;
        }

        if ($workPlaceBudget < array_sum($workPlaceActivityUsedBudget)) {
            return [
                'inBudget' => false,
                'overBudget' => $this->round_up(array_sum($workPlaceActivityUsedBudget) - $workPlaceBudget, 2),
                'budget' => $workPlaceBudget,
                'workPlaceActivityCostForHour' => $workPlaceActivityCostForHour,
                'freeBudgetBeforeChange' => $workPlaceBudget - array_sum($workPlaceActivityBudgetBeforeChange),
                'dataNegativeValueKeys' => $extraDataNegativeValueKeys
            ];
        }

        return ['inBudget' => true];
    }

    private function createApproveRequest($overBudget): int
    {
        $approveRequest = new \viki\Service\Models\Elequent\Approvement();
        $approveRequest->work_place_id = $this->workplace;
        $approveRequest->date = sprintf('%04d-%02d-01', $this->year, $this->month);
        $approveRequest->creator_id = Auth::user()->id;
        $approveRequest->status = \viki\Service\Models\Elequent\Approvement::STATUS_NEW;
        $approveRequest->type_id = \viki\Service\Models\Elequent\Approvement::TYPE_APPR_OBJECT;
        $approveRequest->sum_above_budget = $overBudget;

        $approveRequest->save();

        // Send notification emails to managers
        $workPlace = WorkPlace::find($this->workplace);
        $regions = $workPlace->region()->get();

        foreach ($regions as $region) {
            $managers = $region->managers()->get();

            foreach ($managers as $manager) {
                $mail = \Illuminate\Support\Facades\Mail::to($manager->email);
                $mail->send(new \viki\Service\Mail\VikiRequestAction([
                    'reason' => 'повишаване на бюджета',
                    'workerplace' => $workPlace->name,
                    'userWhoTriggerChange' => Auth::user()->name,
                    'link' => route('service.approvement')
                ]));
            }
        }

        return $approveRequest->id;
    }

    private function processHoursDataWithApproval($processedData, $budgetCheck, $approvalId): void
    {
        $remainingFreeBudget = $budgetCheck['freeBudgetBeforeChange'];
        
        foreach ($this->hoursData as $workerId => $days) {
            foreach ($days as $day => $hours) {
                if (isset($this->vacationData[$workerId][$day])) continue;
                
                $date = Carbon::create($this->year, $this->month, $day);
                
                if ($hours !== null && $hours !== '') {
                    // Determine if this record should be approved or waiting
                    $status = $this->determineRecordStatus($workerId, $day, $hours, $budgetCheck, $remainingFreeBudget);
                    $this->createOrUpdateRecordWithStatus($workerId, $date, $hours, $status, $approvalId);
                } else {
                    $this->deleteRecord($workerId, $date);
                }
            }
        }
    }

    private function determineRecordStatus($workerId, $day, $hours, $budgetCheck, &$remainingFreeBudget): int
    {
        // Calculate cost for this record
        $activityId = $this->getWorkerActivityId(Worker::find($workerId));
        $hourCost = $budgetCheck['workPlaceActivityCostForHour'][$activityId] ?? 15.0;
        $recordCost = $hours * $hourCost;
        
        // If we have enough free budget, approve and subtract from remaining
        if ($remainingFreeBudget >= $recordCost) {
            $remainingFreeBudget -= $recordCost;
            return WorkerRecord::WORKER_RECORD_APPROVED;
        }
        
        // Not enough budget - set as waiting for approval
        return WorkerRecord::WORKER_RECORD_WAITING;
    }

    private function createOrUpdateRecordWithStatus($workerId, $date, $hours, $status, $approvalId = null): void
    {
        $workerRecordData = [
            'hours' => $hours,
            'day_count' => 0,
            'status' => $status,
            'start_date' => date("Y-m-d"),
            'end_date' => date("Y-m-d"),
            'creator_id' => Auth::id()
        ];

        if ($status !== WorkerRecord::WORKER_RECORD_WAITING) {
            $workerRecordData['old_value'] = $hours;
        }

        if ($approvalId && $status === WorkerRecord::WORKER_RECORD_WAITING) {
            $workerRecordData['approvement_id'] = $approvalId;
        }

        WorkerRecord::updateOrCreate(
            [
                'worker_id' => $workerId,
                'work_place_id' => $this->workplace,
                'date' => $date
            ],
            $workerRecordData
        );
    }

    // Helper methods ported from PresenceController
    private function getHourCostOnWorkPlaceActivityByDate($workPlaceActivity, $date): float
    {
        $workPlaceActivityWorkingHours = $this->getActivityWorkingHoursForDate($workPlaceActivity, $date);

        if ($workPlaceActivityWorkingHours === 0) {
            return 0;
        }

        return ($workPlaceActivity->neto_salary + $workPlaceActivity->social_plus) / $workPlaceActivityWorkingHours;
    }

    private function getActivityWorkingHoursForDate($workPlaceActivity, $date): float
    {
        $workPlaceActivityHours = $workPlaceActivity
            ->hours()
            ->where('date', sprintf('%04d-%02d-01', $this->year, $this->month))
            ->first();

        if ($workPlaceActivityHours) {
            return $workPlaceActivityHours->hours_for_person;
        } else if ($workPlaceActivity->type_working == WorkPlaceActivity::WORKING_STANDART) {
            return (cal_days_in_month(CAL_GREGORIAN, $this->month, $this->year) - count($this->getAllNonWorkingDays($this->month, $this->year))) * 8;
        }

        return 0;
    }

    private function getWorkPlaceActivityWorkersByDate($workPlaceActivity, $date)
    {
        $temporaryWorkers = $workPlaceActivity
            ->temporaryWorkers()->with([
                "workerRecords" => function($q) use($workPlaceActivity, $date) {
                    $q->where('viki_worker_records.work_place_activity_id', '=', $workPlaceActivity->id);
                    $q->where('date', 'like', sprintf('%04d-%02d-%%', $this->year, $this->month));
                }
            ])
            ->wherePivot('date', sprintf('%04d-%02d-01', $this->year, $this->month))
            ->get();

        return Worker::whereHas('workPlaceActivity', function ($q) use ($workPlaceActivity) {
                $q->where('id', '=', $workPlaceActivity->id);
            })->with([
                "workerRecords" => function($q) use($workPlaceActivity, $date) {
                    $q->where('viki_worker_records.work_place_activity_id', '=', $workPlaceActivity->id);
                    $q->where('date', 'like', sprintf('%04d-%02d-%%', $this->year, $this->month));
                }
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

    private function getSpecialDays($month, $year): array
    {
        $specialDays = \viki\Service\Models\Elequent\SpecialDay::where('date', 'like', sprintf('%04d-%02d-%%', $year, $month))->get();

        $specialDaysArr = [];
        foreach ($specialDays as $specialDay) {
            $specialDaysArr[] = (int)substr($specialDay->date, strrpos($specialDay->date, '-') + 1);
        }

        return $specialDaysArr;
    }

    private function getWeekDays($month, $year): array
    {
        $weekDays = [];
        foreach (range(1, cal_days_in_month(CAL_GREGORIAN, $month, $year)) as $day) {
            if (date('N', strtotime($day . '-' . $month . '-' . $year)) >= 6) {
                $weekDays[] = $day;
            }
        }
        return $weekDays;
    }

    private function round_up($value, $precision): float
    {
        $pow = pow(10, $precision);
        return (ceil($pow * $value) + ceil($pow * $value - ceil($pow * $value))) / $pow;
    }
}
