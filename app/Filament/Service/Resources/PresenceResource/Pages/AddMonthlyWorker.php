<?php

namespace App\Filament\Service\Resources\PresenceResource\Pages;

use App\Filament\Service\Resources\PresenceResource;
use App\Filament\Service\Resources\WorkPlaceResource;
use App\Services\Presence\PresenceConfigurationService;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use viki\Service\Models\Elequent\WorkPlace;
use viki\Service\Models\Elequent\WorkPlaceActivity;
use viki\Service\Models\Elequent\Worker;
use viki\Service\Models\Elequent\VikiUser;

class AddMonthlyWorker extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = PresenceResource::class;
    protected static string $view = 'filament.service.resources.presence-resource.pages.add-monthly-worker';
    protected static ?string $title = 'Добавяне на работник';

    public int $workplace;
    public string $monthYear;
    public int $year;
    public int $month;
    public string $normalizedDate;

    public ?WorkPlace $workplaceModel = null;
    public array $workerOptions = [];
    public array $activityOptions = [];

    public ?array $data = [];

    public function mount(int $workplace, string $date): void
    {
        $this->workplace = $workplace;
        $this->monthYear = $date;
        $this->parseMonthYear($date);
        $this->authorizeForWorkplace();

        // Ensure monthly activities exist (copies of base activities when needed)
        PresenceConfigurationService::ensureMonthlyActivities($this->workplace, $this->year, $this->month);

        $this->loadWorkerOptions();
        $this->loadActivityOptions();

        $defaults = [];
        if (!empty($this->workerOptions)) {
            $defaults['worker_id'] = array_key_first($this->workerOptions);
        }
        if (!empty($this->activityOptions)) {
            $defaults['work_place_activity_id'] = array_key_first($this->activityOptions);
        }

        if (!empty($defaults)) {
            $this->form->fill($defaults);
        }
    }

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make([
                    'default' => 1,
                    'md' => 2,
                ])->schema([
                    Forms\Components\Select::make('worker_id')
                        ->label('Работници в региона')
                        ->options(fn () => $this->workerOptions)
                        ->searchable()
                        ->preload()
                        ->optionsLimit(2000)
                        ->required()
                        ->native(false)
                        ->placeholder('Изберете работник')
                        ->columnSpan(1),

                    Forms\Components\Select::make('work_place_activity_id')
                        ->label('Дейност')
                        ->options(fn () => $this->activityOptions)
                        ->preload()
                        ->required()
                        ->native(false)
                        ->placeholder('Изберете дейност')
                        ->columnSpan(1),
                ]),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $formData = $this->form->getState();

        if (empty($formData['worker_id']) || empty($formData['work_place_activity_id'])) {
            $this->showError('Моля изберете работник и дейност.');
            return;
        }

        try {
            // Add worker to current month only
            // Workers will be copied to next month when the month is locked
            PresenceConfigurationService::addWorkerToActivity(
                $this->workplace,
                (int) $formData['work_place_activity_id'],
                (int) $formData['worker_id'],
                $this->monthYear
            );

        } catch (RuntimeException $exception) {
            $this->showError($exception->getMessage());
            return;
        } catch (\Throwable $throwable) {
            report($throwable);
            $this->showError('Възникна неочаквана грешка при добавянето на работника.');
            return;
        }

        $this->showSuccess('Работникът е добавен успешно.');
        $this->redirect($this->getBackUrl());
    }

    public function getSubheading(): ?string
    {
        return sprintf('Месец %s · Обект %s', $this->monthYear, $this->workplaceModel?->name ?? 'неизвестен');
    }

    public function hasWorkers(): bool
    {
        return !empty($this->workerOptions);
    }

    public function hasActivities(): bool
    {
        return !empty($this->activityOptions);
    }

    public function getBackUrl(): string
    {
        return sprintf('/service/presences/monthly/%d/%s', $this->workplace, $this->monthYear);
    }

    public function getManageActivitiesUrl(): string
    {
        return WorkPlaceResource::getUrl('activities', ['record' => $this->workplace]);
    }

    /**
     * Automatically add the worker to the next 2 months after current month
     */
    private function addWorkerToNextMonths(int $workerId, int $activityId): void
    {
        // Get the base activity to find its properties
        $baseActivity = WorkPlaceActivity::find($activityId);
        if (!$baseActivity) {
            return;
        }

        // Parse current month and get next 2 months
        $currentDate = Carbon::createFromFormat('m-Y', $this->monthYear);
        $nextMonths = [
            $currentDate->copy()->addMonth(),     // Next month
            $currentDate->copy()->addMonths(2),   // Month after next
        ];

        foreach ($nextMonths as $futureMonth) {
            try {
                $futureYear = $futureMonth->year;
                $futureMonthNum = $futureMonth->month;
                $futureMonthYear = $futureMonth->format('m-Y');

                // Ensure monthly activities exist for the future month
                PresenceConfigurationService::ensureMonthlyActivities($this->workplace, $futureYear, $futureMonthNum);

                // Find the corresponding monthly activity for the future month
                $normalizedDate = $futureMonth->copy()->startOfMonth()->toDateString();
                $futureMonthlyActivity = WorkPlaceActivity::where('work_place_id', $this->workplace)
                    ->where('copied', WorkPlaceActivity::COPIED_ACTIVITY)
                    ->whereDate('date', $normalizedDate)
                    ->where('activity', $baseActivity->activity)
                    ->where('type_working', $baseActivity->type_working)
                    ->first();

                if (!$futureMonthlyActivity) {
                    // Monthly activity doesn't exist for this future month, skip
                    continue;
                }

                // Add worker to the future month's activity
                PresenceConfigurationService::addWorkerToActivity(
                    $this->workplace,
                    $futureMonthlyActivity->id,
                    $workerId,
                    $futureMonthYear
                );

            } catch (\Exception $e) {
                // Log error but don't stop the process
                // Worker can be added manually to future months if needed
                report($e);
            }
        }
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

    private function loadWorkerOptions(): void
    {
        if (!$this->workplaceModel || !$this->workplaceModel->region_id) {
            $this->workerOptions = [];
            return;
        }

        $endOfMonth = Carbon::parse($this->normalizedDate)->endOfMonth();

        $workers = Worker::where('region_id', $this->workplaceModel->region_id)
            ->where('status', Worker::USER_ACTIVE)
            ->where('start_date', '<=', $endOfMonth->toDateString())
            ->orderBy('name')
            ->orderBy('family_name')
            ->get();

        $this->workerOptions = $workers->mapWithKeys(function (Worker $worker) {
            $fullName = trim($worker->name . ' ' . ($worker->middle_name ? $worker->middle_name . ' ' : '') . $worker->family_name);
            return [$worker->id => $fullName];
        })->toArray();
    }

    private function loadActivityOptions(): void
    {
        // Load ALL monthly activities (both copied=0 and copied=1)
        $activities = WorkPlaceActivity::where('work_place_id', $this->workplace)
            ->whereDate('date', $this->normalizedDate)
            ->orderBy('activity')
            ->get();

        $this->activityOptions = $activities->mapWithKeys(function (WorkPlaceActivity $activity) {
            return [$activity->id => $activity->activity];
        })->toArray();
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
