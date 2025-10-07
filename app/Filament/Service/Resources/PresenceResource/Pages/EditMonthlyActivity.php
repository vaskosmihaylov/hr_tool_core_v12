<?php

namespace App\Filament\Service\Resources\PresenceResource\Pages;

use App\Filament\Service\Resources\PresenceResource;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use viki\Service\Models\Elequent\HoursActivityByMonth;
use viki\Service\Models\Elequent\WorkPlace;
use viki\Service\Models\Elequent\WorkPlaceActivity;
use Viki\Service\Models\Elequent\VikiUser;

class EditMonthlyActivity extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = PresenceResource::class;
    protected static string $view = 'filament.service.resources.presence-resource.pages.edit-monthly-activity';
    protected static ?string $title = 'Редактирай дейност';

    public int $workplace;
    public string $monthYear;
    public int $year;
    public int $month;
    public string $normalizedDate;
    public ?WorkPlace $workplaceModel = null;
    public WorkPlaceActivity $activityModel;

    public ?array $data = [];

    public function mount(int $workplace, string $date, int $activity): void
    {
        $this->workplace = $workplace;
        $this->monthYear = $date;
        $this->parseMonthYear($date);
        $this->authorizeForWorkplace();
        $this->loadActivity($activity);

        $hoursRecord = HoursActivityByMonth::where('work_place_activity_id', $this->activityModel->id)
            ->where('date', $this->normalizedDate)
            ->first();

        $this->form->fill([
            'activity_name' => $this->activityModel->activity,
            'worker_count' => $this->activityModel->worker_count,
            'type_working' => $this->activityModel->type_working,
            'neto_salary' => $this->activityModel->neto_salary,
            'social_plus' => $this->activityModel->social_plus,
            'hours_for_person' => $hoursRecord?->hours_for_person ?? 0,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('activity_name')
                    ->label('Дейност')
                    ->disabled()
                    ->dehydrated(false),

                Forms\Components\TextInput::make('worker_count')
                    ->label('Брой работници')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->maxValue(100),

                Forms\Components\Select::make('type_working')
                    ->label('Работно време')
                    ->options([
                        WorkPlaceActivity::WORKING_STANDART => 'стандартно',
                        WorkPlaceActivity::WORKING_BY_HOURS => 'сумарно',
                    ])
                    ->required(),

                Forms\Components\TextInput::make('neto_salary')
                    ->label('Нето цена')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->rule('regex:/^\\d*(\\.\\d{1,3})?$/')
                    ->suffix('лв'),

                Forms\Components\TextInput::make('social_plus')
                    ->label('Социален пакет')
                    ->numeric()
                    ->minValue(0)
                    ->rule('regex:/^\\d*(\\.\\d{1,3})?$/')
                    ->suffix('лв'),

                Forms\Components\TextInput::make('hours_for_person')
                    ->label('Часове')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->maxValue(300),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        try {
            DB::transaction(function () use ($data) {
                $this->activityModel->update([
                    'worker_count' => $data['worker_count'],
                    'type_working' => $data['type_working'],
                    'neto_salary' => $data['neto_salary'],
                    'social_plus' => $data['social_plus'],
                ]);

                HoursActivityByMonth::updateOrCreate(
                    [
                        'work_place_activity_id' => $this->activityModel->id,
                        'date' => $this->normalizedDate,
                    ],
                    [
                        'hours_for_person' => $data['hours_for_person'],
                        'created_by' => Auth::id(),
                    ]
                );
            });
        } catch (\Throwable $throwable) {
            report($throwable);
            $this->showError('Възникна грешка при запазването на промените.');
            return;
        }

        $this->showSuccess('Дейността е обновена успешно.');
        $this->redirect($this->getConfigUrl());
    }

    public function getSubheading(): ?string
    {
        return sprintf('Редактиране на дейност за %s', $this->monthYear);
    }

    private function parseMonthYear(string $date): void
    {
        try {
            $parsed = Carbon::createFromFormat('m-Y', $date)->startOfMonth();
        } catch (\Exception $exception) {
            abort(404, 'Невалиден месец.');
        }

        $this->year = (int) $parsed->format('Y');
        $this->month = (int) $parsed->format('m');
        $this->normalizedDate = $parsed->toDateString();
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

    private function loadActivity(int $activityId): void
    {
        $activity = WorkPlaceActivity::findOrFail($activityId);

        if ($activity->work_place_id !== $this->workplace || empty($activity->date)) {
            abort(404, 'Дейността не може да бъде редактирана.');
        }

        $activityDate = Carbon::parse($activity->date);
        if ((int) $activityDate->format('Y') !== $this->year || (int) $activityDate->format('m') !== $this->month) {
            abort(404, 'Дейността не е част от избрания месец.');
        }

        $this->activityModel = $activity;
    }

    private function getConfigUrl(): string
    {
        return sprintf('/service/presences/config/%d/%s', $this->workplace, $this->monthYear);
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
