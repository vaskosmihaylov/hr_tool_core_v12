<?php

namespace App\Filament\Service\Pages;

use Filament\Pages\Page;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Grid;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use viki\Service\Models\Elequent\Region;
use viki\Service\Models\Elequent\WorkPlace;
use viki\Service\Models\Elequent\Client;
use viki\Service\Models\Elequent\Worker;
use viki\Service\Models\Elequent\VikiUser;
use viki\Service\Models\Elequent\WorkerRecord;
use viki\Service\Models\Elequent\WorkPlaceActivity;
use viki\Service\Models\Elequent\Vacation;
use viki\Service\Http\Controllers\ReportController;
use Filament\Notifications\Notification;
use Carbon\Carbon;

class Reports extends Page implements HasForms, HasActions
{
    use InteractsWithForms, InteractsWithActions;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'filament.service.pages.reports';

    protected static ?string $navigationLabel = 'Справки';

    protected static ?string $title = 'Справки';

    protected static ?string $navigationGroup = '📊 Отчети';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'reports';

    public ?array $reportData = [];
    public ?array $filters = [];
    public bool $showResults = false;
    
    // Add rate limiting properties
    private const MAX_REQUESTS_PER_MINUTE = 10;

    public function mount(): void
    {
        $this->filters = [
            'month_id' => now()->format('m'),
            'year_id' => now()->year,
            'region_id' => [],
            'workplace_id' => [],
            'client_id' => [],
            'worker_id' => null,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateReport')
                ->label('Генерирай отчет')
                ->icon('heroicon-o-chart-bar')
                ->color('primary')
                ->form([
                    Section::make('Параметри за отчет')
                        ->description('Изберете критерии за генериране на отчет')
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    Select::make('month_id')
                                        ->label('Месец')
                                        ->options([
                                            '01' => 'Януари', '02' => 'Февруари', '03' => 'Март',
                                            '04' => 'Април', '05' => 'Май', '06' => 'Юни',
                                            '07' => 'Юли', '08' => 'Август', '09' => 'Септември',
                                            '10' => 'Октомври', '11' => 'Ноември', '12' => 'Декември'
                                        ])
                                        ->default(now()->format('m'))
                                        ->required()
                                        ->searchable(),

                                    Select::make('year_id')
                                        ->label('Година')
                                        ->options(array_combine(
                                            range(2020, 2040),
                                            range(2020, 2040)
                                        ))
                                        ->default(now()->year)
                                        ->required()
                                        ->searchable(),
                                ]),

                            Grid::make(2)
                                ->schema([
                                    Select::make('region_id')
                                        ->label('Регион')
                                        ->options(function () {
                                            return $this->getRegionOptions();
                                        })
                                        ->multiple()
                                        ->searchable()
                                        ->preload()
                                        ->live(),

                                    Select::make('workplace_id')
                                        ->label('Работно място')
                                        ->options(function (callable $get) {
                                            return $this->getWorkplaceOptions($get('region_id'));
                                        })
                                        ->multiple()
                                        ->searchable()
                                        ->preload(),
                                ]),

                            Grid::make(2)
                                ->schema([
                                    Select::make('client_id')
                                        ->label('Клиент')
                                        ->options(function (callable $get) {
                                            return $this->getClientOptions($get('region_id'));
                                        })
                                        ->multiple()
                                        ->searchable()
                                        ->preload(),

                                    Select::make('worker_id')
                                        ->label('Работник')
                                        ->options(function (callable $get) {
                                            return $this->getWorkerOptions($get('region_id'));
                                        })
                                        ->searchable()
                                        ->preload(),
                                ]),
                        ])
                        ->collapsible(),
                ])
                ->action(function (array $data) {
                    $this->generateReportData($data);
                }),

            Action::make('exportExcel')
                ->label('Експорт Excel')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->visible(fn () => $this->showResults)
                ->action(function () {
                    $this->exportToExcel();
                }),

            Action::make('clearFilters')
                ->label('Изчисти резултати')
                ->icon('heroicon-o-x-mark')
                ->color('warning')
                ->visible(fn () => $this->showResults)
                ->action(function () {
                    $this->clearResults();
                }),
        ];
    }

    /**
     * Get region options with role-based filtering
     */
    private function getRegionOptions(): array
    {
        $user = Auth::user();
        
        if ($user->hasRole('manager') || $user->hasRole('supervisor')) {
            $manRegionId = VikiUser::getCurrentUserRegionId($user->id);
            return Region::whereIn('id', $manRegionId)
                ->whereNotNull('name')
                ->where('name', '!=', '')
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray();
        }
        
        return Region::whereNotNull('name')
            ->where('name', '!=', '')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Get workplace options with optimized filtering
     */
    private function getWorkplaceOptions(?array $selectedRegions): array
    {
        $user = Auth::user();
        
        if ($user->hasRole('supervisor')) {
            $vikiUser = VikiUser::find($user->id);
            if ($vikiUser) {
                $query = $vikiUser->workPlaces()
                    ->whereNotNull('name')
                    ->where('name', '!=', '');
                
                if (!empty($selectedRegions)) {
                    $query->whereIn('region_id', $selectedRegions);
                }
                
                return $query->orderBy('name')
                    ->pluck('name', 'id')
                    ->toArray();
            }
            return [];
        }
        
        if ($user->hasRole('manager')) {
            $manRegionId = VikiUser::getCurrentUserRegionId($user->id);
            $regions = !empty($selectedRegions) ? $selectedRegions : $manRegionId;
            
            return WorkPlace::whereIn('region_id', $regions)
                ->whereNotNull('name')
                ->where('name', '!=', '')
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray();
        }
        
        $query = WorkPlace::whereNotNull('name')
            ->where('name', '!=', '');
            
        if (!empty($selectedRegions)) {
            $query->whereIn('region_id', $selectedRegions);
        }
        
        return $query->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Get client options with optimized filtering
     */
    private function getClientOptions(?array $selectedRegions): array
    {
        $user = Auth::user();
        
        if ($user->hasRole('manager') || $user->hasRole('supervisor')) {
            $manRegionId = VikiUser::getCurrentUserRegionId($user->id);
            $regions = !empty($selectedRegions) ? $selectedRegions : $manRegionId;
            
            return Client::with('workplaces')
                ->whereHas('regions', function ($q) use ($regions) {
                    $q->whereIn('id', $regions);
                })
                ->whereNotNull('name')
                ->where('name', '!=', '')
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray();
        }
        
        $query = Client::whereNotNull('name')
            ->where('name', '!=', '');
            
        if (!empty($selectedRegions)) {
            $query->whereHas('regions', function ($q) use ($selectedRegions) {
                $q->whereIn('id', $selectedRegions);
            });
        }
        
        return $query->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Get worker options with optimized loading
     */
    private function getWorkerOptions(?array $selectedRegions): array
    {
        $user = Auth::user();
        
        if ($user->hasRole('manager') || $user->hasRole('supervisor')) {
            $manRegionId = VikiUser::getCurrentUserRegionId($user->id);
            $regions = !empty($selectedRegions) ? $selectedRegions : $manRegionId;
            
            return Worker::whereIn('region_id', $regions)
                ->whereNotNull('name')
                ->whereNotNull('family_name')
                ->where('name', '!=', '')
                ->where('family_name', '!=', '')
                ->orderBy('family_name')
                ->orderBy('name')
                ->get()
                ->mapWithKeys(function ($worker) {
                    $fullName = trim($worker->name . ' ' . $worker->family_name);
                    return $fullName ? [$worker->id => $fullName] : [];
                })
                ->toArray();
        }
        
        $query = Worker::whereNotNull('name')
            ->whereNotNull('family_name')
            ->where('name', '!=', '')
            ->where('family_name', '!=', '');
            
        if (!empty($selectedRegions)) {
            $query->whereIn('region_id', $selectedRegions);
        }
        
        return $query->orderBy('family_name')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function ($worker) {
                $fullName = trim($worker->name . ' ' . $worker->family_name);
                return $fullName ? [$worker->id => $fullName] : [];
            })
            ->toArray();
    }

    /**
     * Generate report data using optimized service
     */
    public function generateReportData(array $filters): void
    {
        try {
            // Rate limiting check
            if (!$this->checkRateLimit()) {
                Notification::make()
                    ->title('Твърде много заявки')
                    ->body('Моля изчакайте преди да генерирате нов отчет')
                    ->warning()
                    ->send();
                return;
            }

            $reportsService = app(\App\Services\ReportsService::class);
            
            $this->reportData = $reportsService->generateReportData($filters);
            $this->filters = $filters;
            $this->showResults = true;

            // Log activity
            activity()
                ->performedOn(Auth::user())
                ->causedBy(Auth::user())
                ->log('Генериран отчет за ' . $filters['month_id'] . '/' . $filters['year_id']);

            Notification::make()
                ->title('Отчет генериран успешно')
                ->body(sprintf(
                    'Отчетът е генериран за %s/%s с %d работника и %d записа',
                    $filters['month_id'],
                    $filters['year_id'],
                    $this->reportData['summary']['total_workers'],
                    $this->reportData['summary']['total_records']
                ))
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Грешка при генериране на отчет')
                ->body($e->getMessage())
                ->danger()
                ->send();
            
            // Log error
            \Log::error('Reports generation error', [
                'user_id' => Auth::id(),
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Export to Excel with error handling
     */
    public function exportToExcel(): void
    {
        if (empty($this->reportData)) {
            Notification::make()
                ->title('Грешка')
                ->body('Моля първо генерирайте отчет')
                ->danger()
                ->send();
            return;
        }

        try {
            $url = route('service.reports.export-excel', $this->filters);
            $this->js("window.open('$url', '_blank')");
            
            Notification::make()
                ->title('Excel експорт')
                ->body('Excel файлът се генерира в нов прозорец...')
                ->success()
                ->send();
                
        } catch (\Exception $e) {
            Notification::make()
                ->title('Грешка при експорт')
                ->body('Възникна грешка при генериране на Excel файла')
                ->danger()
                ->send();
                
            \Log::error('Excel export error', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear results and reset state
     */
    public function clearResults(): void
    {
        $this->showResults = false;
        $this->reportData = [];
        
        Notification::make()
            ->title('Резултати изчистени')
            ->body('Данните са премахнати от екрана')
            ->info()
            ->send();
    }

    /**
     * Simple rate limiting implementation
     */
    private function checkRateLimit(): bool
    {
        $key = 'reports_rate_limit:' . Auth::id();
        $requests = \Cache::get($key, 0);
        
        if ($requests >= self::MAX_REQUESTS_PER_MINUTE) {
            return false;
        }
        
        \Cache::put($key, $requests + 1, 60); // 1 minute TTL
        return true;
    }

    public function getTitle(): string
    {
        if ($this->showResults && !empty($this->filters)) {
            $months = [
                '01' => 'Януари', '02' => 'Февруари', '03' => 'Март',
                '04' => 'Април', '05' => 'Май', '06' => 'Юни',
                '07' => 'Юли', '08' => 'Август', '09' => 'Септември',
                '10' => 'Октомври', '11' => 'Ноември', '12' => 'Декември'
            ];
            
            $monthName = $months[$this->filters['month_id']] ?? $this->filters['month_id'];
            return "Справки за {$monthName} {$this->filters['year_id']}";
        }
        
        return 'Справки';
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            $user = Auth::user();
            $cacheKey = 'navigation_badge:' . $user->id;
            
            return \Cache::remember($cacheKey, 300, function () use ($user) {
                $query = WorkerRecord::where('date', 'like', now()->format('Y-m') . '%');
                
                if ($user->hasRole('supervisor')) {
                    $vikiUser = VikiUser::find($user->id);
                    if ($vikiUser) {
                        $workplaceIds = $vikiUser->workPlaces()->pluck('id')->toArray();
                        $query->whereIn('work_place_id', $workplaceIds);
                    }
                } elseif ($user->hasRole('manager')) {
                    $manRegionId = VikiUser::getCurrentUserRegionId($user->id);
                    $query->whereHas('workPlace', function ($q) use ($manRegionId) {
                        $q->whereIn('region_id', $manRegionId);
                    });
                }

                return (string) $query->distinct('worker_id')->count('worker_id');
            });
        } catch (\Exception $e) {
            \Log::error('Navigation badge error', ['error' => $e->getMessage()]);
            return '0';
        }
    }
}
