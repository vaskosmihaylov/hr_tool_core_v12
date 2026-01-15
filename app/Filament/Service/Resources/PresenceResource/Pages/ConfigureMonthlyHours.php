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
                            ->helperText('Колко часа се очаква този работник даработи през ' . $this->getMonthName())
                            ->columnSpan(1),

                        Forms\Components\Placeholder::make("calculated_rate_{$activity->id}")
                            ->label('Цена на час')
                            ->content(function () use ($activity) {
                                $hours = $this->data['hours'][$activity->id] ?? 0;
                                if ($hours > 0) {
                                    $salary = $activity->neto_salary;
                                    $rate = $salary / $hours;
                                    return number_format($rate, 2) . ' лв/час';
                                }
                                return '-';
                            })
                            ->columnSpan(1),

                        Forms\Components\Placeholder::make("salary_{$activity->id}")
                            ->label('Месечна заплата')
                            ->content(function () use ($activity) {
                                return number_format($activity->neto_salary, 2) . ' лв';
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
            }

            DB::commit();
            $this->showSuccess('Часовете са запазени успешно.');
            $this->redirect($this->getBackUrl());
        } catch (\Exception $e) {
            DB::rollBack();
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
        // Load only "сумарно" (WORKING_BY_HOURS) activities for this month
        // Note: Load ALL monthly activities regardless of 'copied' flag to avoid "invisible activity" bugs
        $this->activities = WorkPlaceActivity::where('work_place_id', $this->workplace)
            ->whereDate('date', $this->normalizedDate)
            ->where('type_working', WorkPlaceActivity::WORKING_BY_HOURS)
            ->orderBy('activity')
            ->get();
    }

    private function initializeFormData(): void
    {
        $this->data['hours'] = [];

        foreach ($this->activities as $activity) {
            // Check if hours are already configured for this activity
            $hoursConfig = HoursActivityByMonth::where('work_place_activity_id', $activity->id)
                ->where('date', $this->normalizedDate)
                ->first();

            $this->data['hours'][$activity->id] = $hoursConfig ? $hoursConfig->hours_for_person : null;
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
