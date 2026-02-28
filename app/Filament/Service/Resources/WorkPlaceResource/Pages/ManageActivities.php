<?php

namespace App\Filament\Service\Resources\WorkPlaceResource\Pages;

use App\Filament\Service\Resources\WorkPlaceResource;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use viki\Service\Models\Elequent\WorkPlace;
use viki\Service\Models\Elequent\WorkPlaceActivity;
use viki\Service\Models\Elequent\WorkPlaceActivityHoursPerDay;

class ManageActivities extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string $resource = WorkPlaceResource::class;

    protected static string $view = 'filament.service.resources.work-place-resource.pages.manage-activities';

    public WorkPlace $record;

    public function mount(WorkPlace $record): void
    {
        abort_unless(WorkPlaceResource::canEdit($record), 403);

        // Additional check: Supervisors cannot access activities management
        $user = auth()->user();
        if ($user && $user->hasRole('supervisor')) {
            throw new AccessDeniedHttpException('Нямате достъп до управление на дейности.');
        }

        $this->record = $record;

        if ($this->tableFilters === null) {
            $this->tableFilters = ['scope' => 'permanent'];
        }
    }

    public function getTitle(): string
    {
        return 'Дейности · ' . $this->record->name;
    }

    public function getBreadcrumbs(): array
    {
        return [
            WorkPlaceResource::getUrl('index') => 'Обекти',
            WorkPlaceResource::getUrl('edit', ['record' => $this->record]) => 'Редакция',
            'Дейности' => null,
        ];
    }

    protected function getTableQuery(): Builder
    {
        return WorkPlaceActivity::query()
            ->with('hoursPerDay')
            ->where('work_place_id', $this->record->getKey())
            ->orderByDesc('created_at');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('activity')
                ->label('Дейност')
                ->searchable()
                ->sortable()
                ->weight('medium'),

            TextColumn::make('worker_count')
                ->label('Работници')
                ->sortable()
                ->badge()
                ->color('info'),

            BadgeColumn::make('type_working')
                ->label('Тип работа')
                ->getStateUsing(fn (WorkPlaceActivity $record): string =>
                    $record->type_working === WorkPlaceActivity::WORKING_STANDART ? 'Стандартно' : 'Сумарно'
                )
                ->colors([
                    'primary' => static fn ($state): bool => $state === 'Стандартно',
                    'warning' => static fn ($state): bool => $state === 'Сумарно',
                ]),

            TextColumn::make('neto_salary')
                ->label('Нето заплата')
                ->money('EUR')
                ->sortable(),

            TextColumn::make('hours_per_day')
                ->label('Часове на ден')
                ->getStateUsing(fn (WorkPlaceActivity $record): string =>
                    $record->hoursPerDay?->hours_per_day ? (string) $record->hoursPerDay->hours_per_day : '—'
                )
                ->alignCenter(),

            TextColumn::make('total_cost')
                ->label('Общо разходи')
                ->getStateUsing(fn (WorkPlaceActivity $record): float =>
                    $record->neto_salary ?? 0
                )
                ->money('EUR')
                ->sortable(),

            TextColumn::make('date')
                ->label('Планирана дата')
                ->date('d.m.Y')
                ->sortable()
                ->placeholder('Постоянна')
                ->visible(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'super_admin']) ?? false),

            TextColumn::make('createdBy.name')
                ->label('Създадена от')
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('created_at')
                ->label('Създадена на')
                ->dateTime('d.m.Y H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    protected function getTableFilters(): array
    {
        return [
            SelectFilter::make('scope')
                ->label('Вид дейности')
                ->options([
                    'permanent' => 'Постоянни',
                    'planned' => 'Планирани',
                    'all' => 'Всички',
                ])
                ->default('permanent')
                ->query(function (Builder $query, array $data): Builder {
                    return match ($data['value'] ?? 'permanent') {
                        'planned' => $query->whereNotNull('date'),
                        'all' => $query,
                        default => $query->whereNull('date'),
                    };
                }),

            SelectFilter::make('type_working')
                ->label('Тип работа')
                ->options([
                    WorkPlaceActivity::WORKING_STANDART => 'Стандартно',
                    WorkPlaceActivity::WORKING_BY_HOURS => 'Сумарно',
                ]),
        ];
    }

    protected function getTableHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Добави дейност')
                ->icon('heroicon-s-plus')
                ->color('success')
                ->form($this->getActivityFormSchema())
                ->action(function (array $data): void {
                    $payload = $this->prepareActivityData($data);
                    $hoursPerDay = Arr::pull($payload, 'hours_per_day');

                    $activity = WorkPlaceActivity::query()->create(array_merge(
                        $payload,
                        [
                            'work_place_id' => $this->record->getKey(),
                            'created_by' => auth()->id(),
                        ]
                    ));

                    if ($hoursPerDay !== null) {
                        WorkPlaceActivityHoursPerDay::create($hoursPerDay, $activity->getKey());
                    }

                    Notification::make()
                        ->success()
                        ->title('Дейността е създадена')
                        ->body('Дейността беше добавена към обекта.')
                        ->send();
                })
                ->modalHeading('Нова дейност')
                ->modalSubmitActionLabel('Запази')
                ->modalWidth('7xl'),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Action::make('edit')
                ->label('Редакция')
                ->icon('heroicon-s-pencil-square')
                ->color('primary')
                ->form($this->getActivityFormSchema())
                ->fillForm(fn (WorkPlaceActivity $record): array => [
                    'activity' => $record->activity,
                    'worker_count' => $record->worker_count,
                    'type_working' => $record->type_working,
                    'neto_salary' => $record->neto_salary,
                    'hours_per_day' => $record->hoursPerDay?->hours_per_day,
                ])
                ->action(function (WorkPlaceActivity $record, array $data): void {
                    $payload = $this->prepareActivityData($data);
                    $hoursPerDay = Arr::pull($payload, 'hours_per_day');

                    $record->update($payload);

                    if ($hoursPerDay !== null) {
                        WorkPlaceActivityHoursPerDay::create($hoursPerDay, $record->getKey());
                    }

                    Notification::make()
                        ->success()
                        ->title('Дейността е обновена')
                        ->body('Промените бяха записани успешно.')
                        ->send();
                })
                ->modalHeading('Редакция на дейност')
                ->modalSubmitActionLabel('Запази')
                ->modalWidth('7xl'),

            DeleteAction::make()
                ->label('Изтриване')
                ->icon('heroicon-s-trash')
                ->modalHeading('Изтриване на дейност')
                ->modalDescription('Сигурни ли сте, че искате да изтриете тази дейност?')
                ->successNotificationTitle('Дейността беше изтрита'),
        ];
    }

    protected function getTableBulkActions(): array
    {
        return [
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make()
                    ->label('Изтриване'),
            ]),
        ];
    }

    protected function getActivityFormSchema(): array
    {
        return [
            Section::make()
                ->schema([
                    TextInput::make('activity')
                        ->label('Име на дейността')
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(2),

                    TextInput::make('worker_count')
                        ->label('Брой работници')
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->default(1)
                        ->columnSpan(1),

                    Select::make('type_working')
                        ->label('Тип работа')
                        ->options([
                            WorkPlaceActivity::WORKING_STANDART => 'Стандартно',
                            WorkPlaceActivity::WORKING_BY_HOURS => 'Сумарно',
                        ])
                        ->required()
                        ->default(WorkPlaceActivity::WORKING_STANDART)
                        ->columnSpan(1),

                    TextInput::make('neto_salary')
                        ->label('Нето заплата (€)')
                        ->required()
                        ->numeric()
                        ->minValue(0)
                        ->step(0.0001)
                        ->prefix('€')
                        ->columnSpan(1),

                    TextInput::make('hours_per_day')
                        ->label('Часове на ден')
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(24)
                        ->default(8)
                        ->columnSpan(1),
                ])
                ->columns([
                    'sm' => 1,
                    'md' => 2,
                    'xl' => 3,
                ]),
        ];
    }

    protected function prepareActivityData(array $data): array
    {
        $prepared = [
            'activity' => Arr::get($data, 'activity'),
            'worker_count' => (int) Arr::get($data, 'worker_count', 1),
            'type_working' => (int) Arr::get($data, 'type_working', WorkPlaceActivity::WORKING_STANDART),
            'neto_salary' => (float) Arr::get($data, 'neto_salary', 0),
            'hours_per_day' => Arr::has($data, 'hours_per_day') ? (int) Arr::get($data, 'hours_per_day') : null,
        ];

        return $prepared;
    }
}
