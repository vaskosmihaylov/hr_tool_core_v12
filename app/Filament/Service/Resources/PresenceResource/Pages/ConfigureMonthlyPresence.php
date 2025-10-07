<?php

namespace App\Filament\Service\Resources\PresenceResource\Pages;

use App\Filament\Service\Resources\PresenceResource;
use App\Services\Presence\PresenceConfigurationService;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use viki\Service\Models\Elequent\HoursActivityByMonth;
use viki\Service\Models\Elequent\WorkPlace;
use viki\Service\Models\Elequent\WorkPlaceActivity;
use viki\Service\Models\Elequent\WorkerRecord;
use Viki\Service\Models\Elequent\VikiUser;

class ConfigureMonthlyPresence extends Page
{
    protected static string $resource = PresenceResource::class;
    protected static string $view = 'filament.service.resources.presence-resource.pages.configure-monthly-presence';
    protected static ?string $title = 'Настройки присъствена форма';

    public int $workplace;
    public string $monthYear;
    public int $year;
    public int $month;
    public string $normalizedDate;

    /** @var Collection<int, WorkPlaceActivity> */
    public Collection $activities;
    public array $hoursByActivity = [];
    public ?WorkPlace $workplaceModel = null;

    public function mount(int $workplace, string $date): void
    {
        $this->workplace = $workplace;
        $this->monthYear = $date;
        $this->parseMonthYear($date);
        $this->authorizeForWorkplace();

        PresenceConfigurationService::ensureMonthlyActivities($this->workplace, $this->year, $this->month);

        $this->loadData();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Назад към присъствена форма')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url($this->getBackUrl()),
            Actions\Action::make('add_activity')
                ->label('Добави дейност')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->url($this->getAddActivityUrl()),
        ];
    }

    public function getTitle(): string
    {
        $workplaceName = $this->workplaceModel?->name ?? 'Неизвестен обект';
        return sprintf('Настройки присъствена форма - месец %s обект %s', $this->month, $workplaceName);
    }

    public function getMonthLabel(): string
    {
        return sprintf('%02d-%d', $this->month, $this->year);
    }

    public function deleteActivity(int $activityId): void
    {
        $activity = WorkPlaceActivity::where('id', $activityId)
            ->where('work_place_id', $this->workplace)
            ->whereYear('date', $this->year)
            ->whereMonth('date', $this->month)
            ->first();

        if (!$activity) {
            $this->showError('Дейността не беше намерена.');
            return;
        }

        if ($activity->date === null) {
            $this->showWarning('Постоянните дейности не могат да бъдат изтривани от тук.');
            return;
        }

        DB::transaction(function () use ($activity) {
            HoursActivityByMonth::where('work_place_activity_id', $activity->id)->delete();
            $activity->temporaryWorkers()->detach();

            WorkerRecord::where('work_place_activity_id', $activity->id)
                ->where('work_place_id', $this->workplace)
                ->whereBetween('date', [
                    $this->normalizedDate,
                    Carbon::parse($this->normalizedDate)->endOfMonth()->toDateString(),
                ])->delete();

            $activity->delete();
        });

        $this->showSuccess('Дейността е изтрита успешно.');
        $this->loadData();
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

    private function loadData(): void
    {
        $this->activities = WorkPlaceActivity::where('work_place_id', $this->workplace)
            ->whereYear('date', $this->year)
            ->whereMonth('date', $this->month)
            ->orderBy('activity')
            ->get();

        $this->hoursByActivity = PresenceConfigurationService::getHoursForActivities($this->activities, $this->normalizedDate);
    }

    private function getBackUrl(): string
    {
        return sprintf('/service/presences/monthly/%d/%s', $this->workplace, $this->getMonthLabel());
    }

    private function getAddActivityUrl(): string
    {
        return sprintf('/service/presences/config/%d/%s/activity/add', $this->workplace, $this->getMonthLabel());
    }

    private function showSuccess(string $message): void
    {
        Notification::make()
            ->title('Успешно')
            ->body($message)
            ->success()
            ->send();
    }

    private function showWarning(string $message): void
    {
        Notification::make()
            ->title('Внимание')
            ->body($message)
            ->warning()
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
