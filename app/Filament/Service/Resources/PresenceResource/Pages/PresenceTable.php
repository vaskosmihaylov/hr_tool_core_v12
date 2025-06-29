<?php

namespace App\Filament\Service\Resources\PresenceResource\Pages;

use App\Filament\Service\Resources\PresenceResource;
use Filament\Resources\Pages\Page;
use Filament\Actions;
use Viki\Service\Models\Elequent\WorkPlace;
use Viki\Service\Models\Elequent\Worker;
use Viki\Service\Models\Elequent\WorkerRecord;
use Carbon\Carbon;

class PresenceTable extends Page
{
    protected static string $resource = PresenceResource::class;

    protected static string $view = 'filament.service.resources.presence-resource.pages.presence-table';

    // Route parameters as class properties
    public int $workplace;
    public ?string $date = null;
    
    // Component data properties
    public $selectedDate;
    public $workplaces;
    public $workers;
    public $presenceData;

    public function mount(): void
    {
        $this->selectedDate = $this->date ? Carbon::parse($this->date) : Carbon::today();
        
        $this->loadData();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('previous_day')
                ->label('Предишен ден')
                ->icon('heroicon-o-chevron-left')
                ->action(fn () => $this->changeDate(-1)),

            Actions\Action::make('today')
                ->label('Днес')
                ->icon('heroicon-o-home')
                ->action(fn () => $this->goToToday()),

            Actions\Action::make('next_day')
                ->label('Следващ ден')
                ->icon('heroicon-o-chevron-right')
                ->action(fn () => $this->changeDate(1)),

            Actions\Action::make('export_pdf')
                ->label('Export PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->action(fn () => $this->exportPdf()),

            Actions\Action::make('export_excel')
                ->label('Export Excel')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->action(fn () => $this->exportExcel()),
        ];
    }

    public function changeDate(int $days): void
    {
        $this->selectedDate = $this->selectedDate->copy()->addDays($days);
        $this->loadData();
    }

    public function goToToday(): void
    {
        $this->selectedDate = Carbon::today();
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
            $this->workers = Worker::where('work_place_id', $this->workplace)
                ->where('status', Worker::WORKER_ACTIVE)
                ->with(['workplace', 'region'])
                ->get();

            $this->presenceData = WorkerRecord::where('work_place_id', $this->workplace)
                ->whereDate('date', $this->selectedDate)
                ->with(['worker', 'activity'])
                ->get()
                ->keyBy('worker_id');
        }
    }

    public function savePresenceData(array $data): void
    {
        foreach ($data as $workerId => $presenceInfo) {
            if (!empty($presenceInfo['hours'])) {
                WorkerRecord::updateOrCreate(
                    [
                        'worker_id' => $workerId,
                        'work_place_id' => $this->workplace,
                        'date' => $this->selectedDate->format('Y-m-d'),
                    ],
                    [
                        'hours' => $presenceInfo['hours'],
                        'work_place_activity_id' => $presenceInfo['activity_id'] ?? null,
                        'status' => WorkerRecord::WORKER_RECORD_WAITING,
                        'creator_id' => auth()->id(),
                    ]
                );
            }
        }

        \Filament\Notifications\Notification::make()
            ->title('Данните за присъствие са запазени успешно!')
            ->success()
            ->send();

        $this->loadData();
    }

    public function exportPdf(): void
    {
        // Implementation for PDF export
        \Filament\Notifications\Notification::make()
            ->title('PDF експорт')
            ->body('PDF експорт функционалността ще бъде добавена скоро')
            ->info()
            ->send();
    }

    public function exportExcel(): void
    {
        // Implementation for Excel export
        \Filament\Notifications\Notification::make()
            ->title('Excel експорт')
            ->body('Excel експорт функционалността ще бъде добавена скоро')
            ->info()
            ->send();
    }

    public function getTitle(): string
    {
        $workplaceName = $this->workplaces[$this->workplace] ?? 'Неизвестно място';
        return "Таблица за присъствие - {$workplaceName} - " . $this->selectedDate->format('d.m.Y');
    }
}
