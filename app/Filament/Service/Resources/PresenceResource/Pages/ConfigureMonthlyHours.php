<?php

namespace App\Filament\Service\Resources\PresenceResource\Pages;

use App\Filament\Service\Resources\PresenceResource;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use viki\Service\Models\Elequent\HoursActivityByMonth;
use viki\Service\Models\Elequent\VikiUser;
use viki\Service\Models\Elequent\WorkPlace;
use viki\Service\Models\Elequent\WorkPlaceActivity;

class ConfigureMonthlyHours extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = PresenceResource::class;
    protected static string $view = 'filament.service.resources.presence-resource.pages.configure-monthly-hours';
    protected static ?string $title = 'Конфигурация на часове за месеца';

    public int $workplace;
    public string $monthYear;
    public int $year;
    public int $month;
    public string $normalizedDate;

    public ?WorkPlace $workplaceModel = null;
    public $activities = [];
    public ?array $data = [];

    public function mount(int $workplace, string $date): void
    {
        $this->workplace = $workplace;
        $this->monthYear = $date;
        $this->parseMonthYear($date);
        $this->authorizeForWorkplace();
        $this->loadActivities();
        $this->initializeFormData();
    }

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::FiveExtraLarge;
    }

    public function form(Form $form): Form
    {
        $schema = [];

        foreach ($this->activities as $activity) {
            $schema[] = Forms\Components\Section::make()
                ->schema([
                    Forms\Components\Grid::make([
                        'default' => 1,
                        'md' => 3,
                    ])->schema([
                        Forms\Components\TextInput::make("hours.{$activity->id}")
                            ->label('Очаквани часове за месеца')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(744) // Max hours in a 31-day month
                            ->step(0.5)
                            ->suffix('часа')
                            ->required()
                            ->helperText('Колко часа се очаква този работник да работи през ' . $this->getMonthName())
                            ->live() // Make it live to update salary calculation
                            ->columnSpan(1),

                        Forms\Components\TextInput::make("hourly_rate.{$activity->id}")
                            ->label('Цена на час')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.0001) // Allow 4 decimal places to match database
                            ->suffix('лв/час')
                            ->required()
                            ->helperText('Нето цена за един час работа')
                            ->live() // Make it live to update salary calculation
                            ->columnSpan(1),

                        Forms\Components\Placeholder::make("salary_{$activity->id}")
                            ->label('Месечна заплата')
                            ->content(function ($get) use ($activity) {
                                $hours = (float) ($get("hours.{$activity->id}") ?? 0);
                                $rate = (float) ($get("hourly_rate.{$activity->id}") ?? 0);
                                if ($hours > 0 && $rate > 0) {
                                    $calculatedSalary = $hours * $rate;
                                    return number_format($calculatedSalary, 4) . ' лв';
                                }
                                return number_format($activity->neto_salary, 4) . ' лв';
                            })
                            ->columnSpan(1),
                    ]),
                ])
                ->heading($activity->activity)
                ->description("Тип работа: Сумарно (почасово)")
                ->icon('heroicon-o-clock')
                ->collapsible();
        }

        if (empty($schema)) {
            $schema[] = Forms\Components\Placeholder::make('no_activities')
                ->content('Няма дейности от тип "Сумарно" за този месец.');
        }

        return $form
            ->schema($schema)
            ->statePath('data');
    }

    public function save(): void
    {
        $formData = $this->form->getState();

        if (empty($formData['hours'])) {
            $this->showError('Няма данни за запазване.');
            return;
        }

        try {
            DB::beginTransaction();

            foreach ($formData['hours'] as $activityId => $hours) {
                if (!is_numeric($hours) || $hours < 0) {
                    continue;
                }

                // Get the hourly rate from the form
                $hourlyRate = $formData['hourly_rate'][$activityId] ?? null;
                
                if (!is_numeric($hourlyRate) || $hourlyRate < 0) {
                    \Log::error("ConfigureMonthlyHours: Invalid hourly rate", [
                        'activity_id' => $activityId,
                        'hourly_rate' => $hourlyRate,
                        'form_data' => $formData
                    ]);
                    continue;
                }

                // Update or create hours configuration
                HoursActivityByMonth::updateOrCreate(
                    [
                        'work_place_activity_id' => $activityId,
                        'date' => $this->normalizedDate,
                    ],
                    [
                        'hours_for_person' => (float) $hours,
                        'created_by' => Auth::id(),
                    ]
                );

                // Back-calculate and update activity's neto_salary
                // neto_salary = hourly_rate × hours_for_person
                $newNetoSalary = (float) $hourlyRate * (float) $hours;
                
                // Get the monthly activity to find its permanent parent
                $monthlyActivity = WorkPlaceActivity::findOrFail($activityId);
                
                // Find the permanent activity (date=NULL, copied=0) with same name and type
                $permanentActivity = WorkPlaceActivity::where('work_place_id', $monthlyActivity->work_place_id)
                    ->whereNull('date')
                    ->where('copied', WorkPlaceActivity::NOT_COPIED_ACTIVITY)
                    ->where('activity', $monthlyActivity->activity)
                    ->where('type_working', $monthlyActivity->type_working)
                    ->first();
                
                if ($permanentActivity) {
                    \Log::info("ConfigureMonthlyHours: Updating PERMANENT activity neto_salary", [
                        'monthly_activity_id' => $activityId,
                        'permanent_activity_id' => $permanentActivity->id,
                        'hours' => $hours,
                        'hourly_rate' => $hourlyRate,
                        'new_neto_salary' => $newNetoSalary,
                    ]);
                    
                    $permanentActivity->update([
                        'neto_salary' => $newNetoSalary,
                    ]);
                } else {
                    // If no permanent activity found, update the monthly activity directly
                    \Log::warning("ConfigureMonthlyHours: No permanent activity found, updating monthly activity", [
                        'monthly_activity_id' => $activityId,
                        'new_neto_salary' => $newNetoSalary,
                    ]);
                    
                    $monthlyActivity->update([
                        'neto_salary' => $newNetoSalary,
                    ]);
                }
            }

            DB::commit();
            $this->showSuccess('Часовете и цените са запазени успешно.');
            $this->redirect($this->getBackUrl());
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("ConfigureMonthlyHours: Save failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->showError('Грешка при запазване: ' . $e->getMessage());
        }
    }

    public function getSubheading(): ?string
    {
        return sprintf('Месец %s · Обект %s', $this->monthYear, $this->workplaceModel?->name ?? 'неизвестен');
    }

    public function getBackUrl(): string
    {
        return sprintf('/service/presences/monthly/%d/%s', $this->workplace, $this->monthYear);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Обратно')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url($this->getBackUrl()),

            Actions\Action::make('save')
                ->label('Запази часовете')
                ->icon('heroicon-o-check')
                ->color('success')
                ->action('save'),
        ];
    }

    private function parseMonthYear(string $date): void
    {
        $parsed = Carbon::createFromFormat('m-Y', $date);
        if (!$parsed || $parsed->format('m-Y') !== $date) {
            abort(404, 'Невалиден месец.');
        }

        $this->year = (int) $parsed->format('Y');
        $this->month = (int) $parsed->format('m');
        $this->normalizedDate = $parsed->copy()->startOfMonth()->toDateString();
    }

    private function authorizeForWorkplace(): void
    {
        $user = VikiUser::find(Auth::id());

        $workplaces = match (true) {
            Auth::user()->hasRole(['admin', 'super_admin']) => WorkPlace::where('status', WorkPlace::WORK_PLACE_ACTIVE)->get(),
            Auth::user()->hasRole('manager') => WorkPlace::where('status', WorkPlace::WORK_PLACE_ACTIVE)
                ->whereIn('region_id', VikiUser::getCurrentUserRegionId(Auth::id()))
                ->get(),
            Auth::user()->hasRole('supervisor') => $user?->activeWorkPlaces()->get() ?? collect(),
            default => collect(),
        };

        if (!$workplaces->pluck('id')->contains($this->workplace)) {
            abort(403, 'Нямате достъп до този обект');
        }

        $this->workplaceModel = WorkPlace::findOrFail($this->workplace);
    }

    private function loadActivities(): void
    {
        // Load only base "сумарно" activities.
        $this->activities = WorkPlaceActivity::where('work_place_id', $this->workplace)
            ->whereNull('date')
            ->where('copied', WorkPlaceActivity::NOT_COPIED_ACTIVITY)
            ->where('type_working', WorkPlaceActivity::WORKING_BY_HOURS)
            ->orderBy('activity')
            ->get();
    }

    private function initializeFormData(): void
    {
        $this->data['hours'] = [];
        $this->data['hourly_rate'] = [];

        foreach ($this->activities as $activity) {
            // Check if hours are already configured for this activity
            $hoursConfig = HoursActivityByMonth::where('work_place_activity_id', $activity->id)
                ->where('date', $this->normalizedDate)
                ->first();

            // Set hours (from config or null)
            $hours = $hoursConfig ? $hoursConfig->hours_for_person : null;
            $this->data['hours'][$activity->id] = $hours;

            // Calculate hourly rate from activity's neto_salary and configured hours
            if ($hours && $hours > 0) {
                $hourlyRate = $activity->neto_salary / $hours;
                $this->data['hourly_rate'][$activity->id] = round($hourlyRate, 4);
            } else {
                // If no hours configured, default to showing current neto_salary as hourly rate
                $this->data['hourly_rate'][$activity->id] = round($activity->neto_salary, 4);
            }
        }

        $this->form->fill($this->data);
    }

    private function getMonthName(): string
    {
        $months = [
            1 => 'Януари', 2 => 'Февруари', 3 => 'Март', 4 => 'Април',
            5 => 'Май', 6 => 'Юни', 7 => 'Юли', 8 => 'Август',
            9 => 'Септември', 10 => 'Октомври', 11 => 'Ноември', 12 => 'Декември'
        ];

        return $months[$this->month] . ' ' . $this->year;
    }

    private function showSuccess(string $message): void
    {
        Notification::make()
            ->title('Успешно')
            ->body($message)
            ->success()
            ->send();
    }

    private function showError(string $message): void
    {
        Notification::make()
            ->title('Грешка')
            ->body($message)
            ->danger()
            ->send();
    }
}
