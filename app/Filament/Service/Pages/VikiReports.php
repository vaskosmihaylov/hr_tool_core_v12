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
use viki\Service\Http\Controllers\ReportController;
use Filament\Notifications\Notification;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Columns\Column;
use Carbon\Carbon;

class VikiReports extends Page implements HasForms, HasActions
{
    use InteractsWithForms, InteractsWithActions;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'filament.service.pages.viki-reports';

    protected static ?string $navigationLabel = 'Viki Отчети';

    protected static ?string $title = 'Viki Отчети';

    protected static ?string $navigationGroup = '📊 Отчети';

    protected static ?int $navigationSort = 2;

    public ?array $reportData = [];
    public ?array $filters = [];
    public bool $showResults = false;

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
                                            $user = Auth::user();
                                            if ($user->hasRole('manager') || $user->hasRole('supervisor')) {
                                                $manRegionId = VikiUser::getCurrentUserRegionId($user->id);
                                                return Region::whereIn('id', $manRegionId)
                                                    ->whereNotNull('name')
                                                    ->where('name', '!=', '')
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id')
                                                    ->filter();
                                            }
                                            return Region::whereNotNull('name')
                                                ->where('name', '!=', '')
                                                ->orderBy('name')
                                                ->pluck('name', 'id')
                                                ->filter();
                                        })
                                        ->multiple()
                                        ->searchable()
                                        ->preload()
                                        ->live(),

                                    Select::make('workplace_id')
                                        ->label('Работно място')
                                        ->options(function (callable $get) {
                                            $selectedRegions = $get('region_id');
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
                                                        ->filter();
                                                }
                                                return collect();
                                            } elseif ($user->hasRole('manager')) {
                                                $manRegionId = VikiUser::getCurrentUserRegionId($user->id);
                                                $regions = !empty($selectedRegions) ? $selectedRegions : $manRegionId;
                                                
                                                return WorkPlace::whereIn('region_id', $regions)
                                                    ->whereNotNull('name')
                                                    ->where('name', '!=', '')
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id')
                                                    ->filter();
                                            }
                                            
                                            $query = WorkPlace::whereNotNull('name')
                                                ->where('name', '!=', '');
                                                
                                            if (!empty($selectedRegions)) {
                                                $query->whereIn('region_id', $selectedRegions);
                                            }
                                            
                                            return $query->orderBy('name')
                                                ->pluck('name', 'id')
                                                ->filter();
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
                                            $selectedRegions = $get('region_id');
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
                                                    ->filter();
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
                                                ->filter();
                                        })
                                        ->multiple()
                                        ->searchable()
                                        ->preload(),

                                    Select::make('worker_id')
                                        ->label('Работник')
                                        ->options(function (callable $get) {
                                            $selectedRegions = $get('region_id');
                                            $user = Auth::user();
                                            
                                            if ($user->hasRole('manager') || $user->hasRole('supervisor')) {
                                                $manRegionId = VikiUser::getCurrentUserRegionId($user->id);
                                                $regions = !empty($selectedRegions) ? $selectedRegions : $manRegionId;
                                                
                                                return Worker::whereIn('region_id', $regions)
                                                    ->whereNotNull('name')
                                                    ->whereNotNull('family_name')
                                                    ->where('name', '!=', '')
                                                    ->where('family_name', '!=', '')
                                                    ->orderBy('name')
                                                    ->orderBy('family_name')
                                                    ->get()
                                                    ->mapWithKeys(function ($worker) {
                                                        $fullName = trim(($worker->name ?? '') . ' ' . ($worker->family_name ?? ''));
                                                        return $fullName ? [$worker->id => $fullName] : [];
                                                    })
                                                    ->filter();
                                            }
                                            
                                            $query = Worker::whereNotNull('name')
                                                ->whereNotNull('family_name')
                                                ->where('name', '!=', '')
                                                ->where('family_name', '!=', '');
                                                
                                            if (!empty($selectedRegions)) {
                                                $query->whereIn('region_id', $selectedRegions);
                                            }
                                            
                                            return $query->orderBy('name')
                                                ->orderBy('family_name')
                                                ->get()
                                                ->mapWithKeys(function ($worker) {
                                                    $fullName = trim(($worker->name ?? '') . ' ' . ($worker->family_name ?? ''));
                                                    return $fullName ? [$worker->id => $fullName] : [];
                                                })
                                                ->filter();
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
                    $url = $this->exportToPdf();
                    if ($url) {
                        // Use JavaScript to open the URL
                        $this->js("window.open('$url', '_blank')");
                    }
                }),

            Action::make('clearFilters')
                ->label('Изчисти резултати')
                ->icon('heroicon-o-x-mark')
                ->color('warning')
                ->visible(fn () => $this->showResults)
                ->action(function () {
                    $this->showResults = false;
                    $this->reportData = [];
                    
                    Notification::make()
                        ->title('Резултати изчистени')
                        ->body('Данните са премахнати от екрана')
                        ->info()
                        ->send();
                }),
        ];
    }

    public function generateReportData(array $filters): void
    {
        $this->filters = $filters;
        $month_id = $filters['month_id'];
        $year_id = $filters['year_id'];
        $region_id = $filters['region_id'] ?? [];
        $workplace_id = $filters['workplace_id'] ?? [];
        $client_id = $filters['client_id'] ?? [];
        $worker_id = $filters['worker_id'] ?? null;

        // Use the existing ReportController logic
        $user = Auth::user();
        $manRegion_id = '';

        if (($user->hasRole('manager')) || ($user->hasRole('supervisor'))) {
            $manRegion_id = VikiUser::getCurrentUserRegionId($user->id);
            $region_id = $manRegion_id;
        }

        $query = WorkerRecord::select(
                'viki_worker_records.worker_id',
                'viki_workers.name',
                'viki_workers.family_name',
                'viki_workers.middle_name',
                'viki_workers.egn',
                'viki_worker_records.work_place_id',
                'viki_work_place.name as workPlaceName',
                'viki_work_place.client_id as clId',
                'viki_work_place.region_id as regId',
                DB::raw('sum(viki_worker_records.hours) as total'),
                DB::raw('group_concat(DISTINCT viki_worker_records.work_place_activity_id) as activities'),
                DB::raw('MIN(viki_worker_records.id) as ID') // Use MIN to get a representative ID
            )
            ->leftJoin('viki_workers', function($join) {
                $join->on('viki_workers.id', '=', 'viki_worker_records.worker_id');
            })
            ->leftJoin('viki_work_place', function($join) {
                $join->on('viki_work_place.id', '=', 'viki_worker_records.work_place_id');
            })
            ->where('viki_worker_records.date', 'like', $year_id . '-' . $month_id . '%');

        // Apply role-based filtering
        if ($user->hasRole('supervisor')) {
            $vikiUser = VikiUser::find($user->id);
            $userWorkplaceIds = $vikiUser->workPlaces()->pluck('id')->toArray();
            $query->whereIn('viki_worker_records.work_place_id', $userWorkplaceIds);
        }

        // Apply filters
        if (!empty($workplace_id)) {
            $query->whereIn('viki_worker_records.work_place_id', $workplace_id);
        }

        if (!empty($region_id)) {
            if (!empty($manRegion_id)) {
                $region_id = $manRegion_id;
            }
            $query->whereIn('viki_work_place.region_id', $region_id);
        }

        if (!empty($client_id)) {
            $query->whereIn('viki_work_place.client_id', $client_id);
        }

        if (!empty($worker_id)) {
            $query->where('viki_worker_records.worker_id', '=', $worker_id);
        }

        // Fixed GROUP BY clause - include all non-aggregated columns
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
        ]);
        
        $workerRecords = $query->get();

        // Calculate salaries using existing logic
        $arraySum = [];
        foreach ($workerRecords as $records) {
            $activitiesArray = explode(",", $records->activities);

            foreach ($activitiesArray as $activity) {
                $workplaceActivity = WorkPlaceActivity::find($activity);
                if ($workplaceActivity !== null) {
                    $workingHours = ReportController::getActivityWorkingHoursForDate(
                        $workplaceActivity, 
                        $year_id . '-' . $month_id
                    );

                    $workPlaceActivityHourPrice = $workingHours != 0 ? 
                        ($workplaceActivity->neto_salary + $workplaceActivity->social_plus) / $workingHours : 0;
                    
                    $hoursByActivity = WorkerRecord::select(
                            'viki_worker_records.work_place_activity_id', 
                            DB::raw('sum(viki_worker_records.hours) as totalHours')
                        )
                        ->where('worker_id', $records->worker_id)
                        ->where('work_place_activity_id', $workplaceActivity->id)
                        ->where('date', 'LIKE', $year_id . '-' . $month_id . '%')
                        ->groupBy('viki_worker_records.work_place_activity_id') // Add groupBy for consistency
                        ->get()->toArray();
                    
                    if (!empty($hoursByActivity)) {
                        $arraySum[$records->ID][] = $workPlaceActivityHourPrice * $hoursByActivity[0]['totalHours'];
                    }
                }
            }
        }

        $newSumArray = [];
        if (!empty($arraySum)) {
            foreach ($arraySum as $key => $allSum) {
                $newSumArray[$key] = array_sum($allSum);
            }
        }

        $this->reportData = [
            'workerRecords' => $workerRecords,
            'arraySum' => $newSumArray,
            'filters' => $filters,
            'summary' => [
                'total_workers' => $workerRecords->unique('worker_id')->count(),
                'total_hours' => $workerRecords->sum('total'),
                'total_salary' => array_sum($newSumArray),
            ]
        ];

        $this->showResults = true;

        // Log activity
        activity()
            ->performedOn(Auth::user())
            ->causedBy(Auth::user())
            ->withProperties(['customProperty' => 'customValue'])
            ->log('пусната обща справка за месец ' . $month_id . ' за година ' . $year_id);

        Notification::make()
            ->title('Отчет генериран успешно')
            ->body('Отчетът е генериран за ' . $month_id . '/' . $year_id . ' с ' . $this->reportData['summary']['total_workers'] . ' работника')
            ->success()
            ->send();
    }

    public function exportToPdf()
    {
        if (empty($this->reportData)) {
            Notification::make()
                ->title('Грешка')
                ->body('Моля първо генерирайте отчет')
                ->danger()
                ->send();
            return;
        }

        // Simply redirect to Excel route with the same filters
        $url = route('service.reports.export-excel', $this->filters);
        
        // Use JavaScript to open in new tab
        $this->js("window.open('$url', '_blank')");
        
        Notification::make()
            ->title('Excel експорт')
            ->body('Excel файлът се генерира в нов прозорец...')
            ->success()
            ->send();
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
            return "Viki Отчети за {$monthName} {$this->filters['year_id']}";
        }
        
        return 'Viki Отчети';
    }

    public static function getNavigationBadge(): ?string
    {
        $user = Auth::user();
        $query = WorkerRecord::where('date', 'like', now()->format('Y-m') . '%');
        
        if ($user->hasRole('supervisor')) {
            $vikiUser = VikiUser::find($user->id);
            $workplaceIds = $vikiUser->workPlaces()->pluck('id')->toArray();
            $query->whereIn('work_place_id', $workplaceIds);
        } elseif ($user->hasRole('manager')) {
            $manRegionId = VikiUser::getCurrentUserRegionId($user->id);
            $query->whereHas('workPlace', function ($q) use ($manRegionId) {
                $q->whereIn('region_id', $manRegionId);
            });
        }

        return (string) $query->distinct('worker_id')->count('worker_id');
    }
}
