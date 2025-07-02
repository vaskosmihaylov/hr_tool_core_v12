<?php

namespace App\Filament\Service\Resources\PresenceResource\Pages;

use App\Filament\Service\Resources\PresenceResource;
use App\Exports\MonthlyPresenceExport;
use Filament\Resources\Pages\Page;
use Filament\Actions;
use Viki\Service\Models\Elequent\WorkPlace;
use Viki\Service\Models\Elequent\Worker;
use Viki\Service\Models\Elequent\WorkerRecord;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class MonthlyPresence extends Page
{
    protected static string $resource = PresenceResource::class;

    protected static string $view = 'filament.service.resources.presence-resource.pages.monthly-presence';

    // Route parameters as class properties
    public int $workplace;
    public ?int $year = null;
    public ?int $month = null;
    
    // Component data properties
    public $workplaces;
    public $monthlyData;

    public function mount(): void
    {
        $this->year = $this->year ?: Carbon::now()->year;
        $this->month = $this->month ?: Carbon::now()->month;
        
        $this->loadData();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('previous_month')
                ->label('Предишен месец')
                ->icon('heroicon-o-chevron-left')
                ->action(fn () => $this->changeMonth(-1)),

            Actions\Action::make('current_month')
                ->label('Текущ месец')
                ->icon('heroicon-o-home')
                ->action(fn () => $this->goToCurrentMonth()),

            Actions\Action::make('next_month')
                ->label('Следващ месец')
                ->icon('heroicon-o-chevron-right')
                ->action(fn () => $this->changeMonth(1)),

            Actions\Action::make('export_monthly_excel')
                ->label('Експорт Excel')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->action(fn () => $this->exportMonthlyExcel()),
        ];
    }

    public function changeMonth(int $months): void
    {
        $date = Carbon::create($this->year, $this->month, 1)->addMonths($months);
        $this->year = $date->year;
        $this->month = $date->month;
        $this->loadData();
    }

    public function goToCurrentMonth(): void
    {
        $this->year = Carbon::now()->year;
        $this->month = Carbon::now()->month;
        $this->loadData();
    }

    public function changeWorkplace(): void
    {
        $this->loadData();
    }

    protected function loadData(): void
    {
        $this->workplaces = WorkPlace::where('status', WorkPlace::WORK_PLACE_ACTIVE)
            ->with('region')
            ->get()
            ->pluck('name', 'id');

        if ($this->workplace) {
            $startDate = Carbon::create($this->year, $this->month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            // Get all workers for this workplace
            $workers = Worker::where('work_place_id', $this->workplace)
                ->where('status', Worker::WORKER_ACTIVE)
                ->get();

            // Get all presence records for the month
            $records = WorkerRecord::where('work_place_id', $this->workplace)
                ->whereBetween('date', [$startDate, $endDate])
                ->with(['worker', 'activity'])
                ->get()
                ->groupBy('worker_id');

            // Calculate monthly statistics
            $this->monthlyData = $workers->map(function ($worker) use ($records, $startDate, $endDate) {
                $workerRecords = $records->get($worker->id, collect());
                
                return [
                    'worker' => $worker,
                    'total_hours' => $workerRecords->sum('hours'),
                    'working_days' => $workerRecords->count(),
                    'average_hours' => $workerRecords->count() > 0 ? round($workerRecords->sum('hours') / $workerRecords->count(), 2) : 0,
                    'records' => $workerRecords->keyBy(fn($record) => Carbon::parse($record->date)->day),
                ];
            });
        }
    }

    public function exportMonthlyExcel(): void
    {
        if (!$this->monthlyData || $this->monthlyData->isEmpty()) {
            \Filament\Notifications\Notification::make()
                ->title('Грешка')
                ->body('Няма данни за експорт')
                ->danger()
                ->send();
            return;
        }

        $params = [
            'workplace' => $this->workplace,
            'year' => $this->year,
            'month' => $this->month
        ];

        $url = route('service.presence.export-monthly', $params);
        $this->js("window.open('$url', '_blank')");

        \Filament\Notifications\Notification::make()
            ->title('Excel експорт')
            ->body('Excel файлът се генерира в нов прозорец...')
            ->success()
            ->send();
    }

    public function getTitle(): string
    {
        $workplaceName = $this->workplaces[$this->workplace] ?? 'Неизвестно място';
        $monthName = Carbon::create($this->year, $this->month, 1)->format('F Y');
        return "Месечен преглед - {$workplaceName} - {$monthName}";
    }

    public function getMonthName(): string
    {
        $bulgarianMonths = [
            1 => 'Януари', 2 => 'Февруари', 3 => 'Март', 4 => 'Април',
            5 => 'Май', 6 => 'Юни', 7 => 'Юли', 8 => 'Август',
            9 => 'Септември', 10 => 'Октомври', 11 => 'Ноември', 12 => 'Декември'
        ];
        
        return $bulgarianMonths[$this->month] . ' ' . $this->year;
    }

    public function getDaysInMonth(): int
    {
        return Carbon::create($this->year, $this->month, 1)->daysInMonth;
    }
}
