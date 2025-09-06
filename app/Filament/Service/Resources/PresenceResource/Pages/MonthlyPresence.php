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
    public $budgetInfo;
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
            $this->processHoursData();
            DB::commit();
            
            $this->hasUnsavedChanges = false;
            $this->reloadData();
            $this->showSuccessNotification('Часовете са запазени успешно.');

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
        $this->loadBudgetInfo();
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

        $workers = Worker::whereIn('id', $workerIdsWithRecords)
            ->where('status', Worker::WORKER_ACTIVE)
            ->orderBy('name')
            ->get();

        $records = WorkerRecord::where('work_place_id', $this->workplace)
            ->whereBetween('date', $dateRange)
            ->with(['worker', 'activity'])
            ->get()
            ->groupBy('worker_id');

        $this->monthlyData = $workers->map(fn($worker) => [
            'worker' => $worker,
            'total_hours' => $records->get($worker->id, collect())->sum('hours'),
            'working_days' => $records->get($worker->id, collect())->count(),
            'average_hours' => $this->calculateAverageHours($records->get($worker->id, collect())),
            'records' => $records->get($worker->id, collect())->keyBy(fn($record) => Carbon::parse($record->date)->day),
        ]);
    }

    private function loadBudgetInfo(): void
    {
        if (!$this->workplaceData) return;

        $monthDate = sprintf('%02d-%d', $this->month, $this->year);
        
        try {
            $budget = $this->workplaceData->getBudgetByDate($monthDate) ?? 0;
            $actualSpending = $this->calculateActualSpending();
            
            $this->budgetInfo = [
                'budget' => $budget,
                'actual' => $actualSpending,
                'remaining' => $budget - $actualSpending,
                'percentage' => $budget > 0 ? ($actualSpending / $budget) * 100 : 0,
            ];
        } catch (\Exception $e) {
            $this->budgetInfo = ['budget' => 0, 'actual' => 0, 'remaining' => 0, 'percentage' => 0];
        }
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

        foreach ($this->monthlyData as $data) {
            $workerId = $data['worker']->id;
            $this->hoursData[$workerId] = [];
            
            for ($day = 1; $day <= $this->getDaysInMonth(); $day++) {
                if (isset($this->vacationData[$workerId][$day])) continue;
                
                $dayRecord = $data['records']->get($day);
                $this->hoursData[$workerId][$day] = $dayRecord ? $dayRecord->hours : null;
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

    private function calculateActualSpending(): float
    {
        if (!$this->monthlyData) return 0;
        
        return $this->monthlyData->sum(fn($data) => $data['total_hours'] * 15); // TODO: Use actual worker rates
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
    private function getManageWorkersAction() { return Actions\Action::make('manage_workers')->label('Управление работници')->icon('heroicon-o-users')->color('secondary')->action('openWorkerManagement'); }

    // Notification helpers
    private function showUnsavedChangesWarning() { Notification::make()->title('Незапазени промени')->body('Имате незапазени промени. Моля запазете ги преди да променяте месеца.')->warning()->send(); }
    private function showSuccessNotification($message) { Notification::make()->title('Успешно')->body($message)->success()->send(); }
    private function showWarningNotification($message) { Notification::make()->title('Внимание')->body($message)->warning()->send(); }
    private function showErrorNotification($message) { Notification::make()->title('Грешка')->body($message)->danger()->send(); }
}
