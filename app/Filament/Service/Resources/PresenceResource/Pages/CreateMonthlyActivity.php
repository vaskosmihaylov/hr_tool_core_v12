<?php

namespace App\Filament\Service\Resources\PresenceResource\Pages;

use App\Filament\Service\Resources\PresenceResource;
use App\Services\Presence\PresenceConfigurationService;
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

class CreateMonthlyActivity extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = PresenceResource::class;
    protected static string $view = 'filament.service.resources.presence-resource.pages.create-monthly-activity';
    protected static ?string $title = 'Нова дейност';

    public int $workplace;
    public string $monthYear;
    public int $year;
    public int $month;
    public string $normalizedDate;
    public ?WorkPlace $workplaceModel = null;

    public ?array $data = [];

    public function mount(int $workplace, string $date): void
    {
        $this->workplace = $workplace;
        $this->monthYear = $date;
        $this->parseMonthYear($date);
        $this->authorizeForWorkplace();

        $this->form->fill([
            'type_working' => WorkPlaceActivity::WORKING_STANDART,
            'worker_count' => 1,
            'neto_salary' => 0,
            'social_plus' => 0,
            'hours_for_person' => 8,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('activity')
                    ->label('Дейност')
                    ->required()
                    ->maxLength(50),

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
                    ->default(0)
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

        if (!PresenceConfigurationService::checkWorkplaceBudget($data, $this->workplace, $this->normalizedDate)) {
            $this->showError('Добавяйки тази дейност надвишавате бюджета на обекта!');
            return;
        }

        try {
            DB::transaction(function () use ($data) {
                $activity = WorkPlaceActivity::create($data, $this->workplace, $this->normalizedDate);
                HoursActivityByMonth::create($data['hours_for_person'], $activity->id, $this->normalizedDate);
            });
        } catch (\Throwable $throwable) {
            report($throwable);
            $this->showError('Възникна грешка при запазването на дейността.');
            return;
        }

        $this->showSuccess('Дейността е добавена успешно.');
        $this->redirect($this->getConfigUrl());
    }

    public function getSubheading(): ?string
    {
        return sprintf('Добавяне на дейност за %s', $this->monthYear);
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
