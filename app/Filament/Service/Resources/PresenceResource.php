<?php

namespace App\Filament\Service\Resources;

use App\Filament\Service\Resources\PresenceResource\Pages;
use App\Filament\Service\Resources\PresenceResource\Widgets\PresenceStatsOverview;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Viki\Service\Models\Elequent\WorkerRecord;
use Viki\Service\Models\Elequent\Worker;
use Viki\Service\Models\Elequent\WorkPlace;
use Viki\Service\Models\Elequent\WorkPlaceActivity;

class PresenceResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = WorkerRecord::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Присъствена форма';

    protected static ?string $modelLabel = 'Записи за присъствие';

    protected static ?string $pluralModelLabel = 'Записи за присъствие';

    protected static ?string $navigationGroup = '⏰ Присъствена форма';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('👤 Информация за работника')
                    ->description('Данни за работника и работното място')
                    ->schema([
                        Select::make('worker_id')
                            ->label('Работник')
                            ->relationship('worker', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpan(1),

                        Select::make('work_place_id')
                            ->label('Работно място')
                            ->relationship('workplace', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpan(1),

                        Select::make('work_place_activity_id')
                            ->label('Дейност')
                            ->relationship('activity', 'activity')
                            ->searchable()
                            ->preload()
                            ->columnSpan(1),
                    ])
                    ->columns(3),

                Section::make('📅 Времева информация')
                    ->description('Дати и работни часове')
                    ->schema([
                        DatePicker::make('date')
                            ->label('Дата')
                            ->required()
                            ->default(now())
                            ->columnSpan(1),

                        TextInput::make('hours')
                            ->label('Часове')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(24)
                            ->step(0.5)
                            ->default(8)
                            ->suffix('ч.')
                            ->columnSpan(1),

                        DatePicker::make('start_date')
                            ->label('Начална дата')
                            ->columnSpan(1),

                        DatePicker::make('end_date')
                            ->label('Крайна дата')
                            ->columnSpan(1),

                        TextInput::make('day_count')
                            ->label('Брой дни')
                            ->numeric()
                            ->minValue(0)
                            ->columnSpan(1),
                    ])
                    ->columns(3),

                Section::make('⚙️ Статус')
                    ->description('Състояние на записа')
                    ->schema([
                        Select::make('status')
                            ->label('Статус')
                            ->options([
                                WorkerRecord::WORKER_RECORD_WAITING => 'Чакащ одобрение',
                                WorkerRecord::WORKER_RECORD_APPROVED => 'Одобрен',
                                WorkerRecord::WORKER_RECORD_DISAPPROVED => 'Отхвърлен',
                                WorkerRecord::WORKER_RECORD_FINISHED => 'Приключен',
                            ])
                            ->required()
                            ->default(WorkerRecord::WORKER_RECORD_WAITING)
                            ->columnSpan(1),

                        TextInput::make('old_value')
                            ->label('Стара стойност')
                            ->numeric()
                            ->step(0.01)
                            ->suffix('ч.')
                            ->helperText('Предишна стойност при редактиране')
                            ->columnSpan(1),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('worker.name')
                    ->label('Работник')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('worker.family_name')
                    ->label('Фамилия')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('workplace.name')
                    ->label('Работно място')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('activity.activity')
                    ->label('Дейност')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                TextColumn::make('date')
                    ->label('Дата')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('hours')
                    ->label('Часове')
                    ->suffix(' ч.')
                    ->sortable()
                    ->alignEnd(),

                BadgeColumn::make('status')
                    ->label('Статус')
                    ->getStateUsing(function (WorkerRecord $record): string {
                        return match($record->status) {
                            WorkerRecord::WORKER_RECORD_WAITING => 'Чакащ',
                            WorkerRecord::WORKER_RECORD_APPROVED => 'Одобрен',
                            WorkerRecord::WORKER_RECORD_DISAPPROVED => 'Отхвърлен',
                            WorkerRecord::WORKER_RECORD_FINISHED => 'Приключен',
                            default => 'Неизвестен'
                        };
                    })
                    ->colors([
                        'warning' => static fn ($state): bool => $state === 'Чакащ',
                        'success' => static fn ($state): bool => $state === 'Одобрен',
                        'danger' => static fn ($state): bool => $state === 'Отхвърлен',
                        'info' => static fn ($state): bool => $state === 'Приключен',
                    ]),

                TextColumn::make('creator.name')
                    ->label('Създаден от')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Създаден на')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        WorkerRecord::WORKER_RECORD_WAITING => 'Чакащ одобрение',
                        WorkerRecord::WORKER_RECORD_APPROVED => 'Одобрен',
                        WorkerRecord::WORKER_RECORD_DISAPPROVED => 'Отхвърлен',
                        WorkerRecord::WORKER_RECORD_FINISHED => 'Приключен',
                    ]),

                SelectFilter::make('worker_id')
                    ->label('Работник')
                    ->relationship('worker', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('work_place_id')
                    ->label('Работно място')
                    ->relationship('workplace', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('date_range')
                    ->form([
                        DatePicker::make('date_from')
                            ->label('От дата'),
                        DatePicker::make('date_to')
                            ->label('До дата'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['date_to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    }),

                Filter::make('current_month')
                    ->label('Текущ месец')
                    ->query(fn (Builder $query): Builder => $query->whereMonth('date', now()->month)
                        ->whereYear('date', now()->year)),

                Filter::make('this_week')
                    ->label('Тази седмица')
                    ->query(fn (Builder $query): Builder => $query->whereBetween('date', [
                        now()->startOfWeek(),
                        now()->endOfWeek()
                    ])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Преглед'),

                Tables\Actions\EditAction::make()
                    ->label('Редактиране'),

                Action::make('approve')
                    ->label('Одобри')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->action(function (WorkerRecord $record) {
                        $record->update(['status' => WorkerRecord::WORKER_RECORD_APPROVED]);
                        \Filament\Notifications\Notification::make()
                            ->title('Записът е одобрен успешно!')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (WorkerRecord $record): bool => 
                        $record->status === WorkerRecord::WORKER_RECORD_WAITING),

                Action::make('disapprove')
                    ->label('Отхвърли')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->action(function (WorkerRecord $record) {
                        $record->update(['status' => WorkerRecord::WORKER_RECORD_DISAPPROVED]);
                        \Filament\Notifications\Notification::make()
                            ->title('Записът е отхвърлен!')
                            ->warning()
                            ->send();
                    })
                    ->visible(fn (WorkerRecord $record): bool => 
                        $record->status === WorkerRecord::WORKER_RECORD_WAITING),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Изтриване'),

                    Tables\Actions\BulkAction::make('approve_selected')
                        ->label('Одобри избраните')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->action(function ($records) {
                            $records->each(fn ($record) => 
                                $record->update(['status' => WorkerRecord::WORKER_RECORD_APPROVED])
                            );
                            \Filament\Notifications\Notification::make()
                                ->title('Избраните записи са одобрени!')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                $user = auth()->user();
                
                if (!$user) {
                    return $query->whereRaw('1 = 0');
                }
                
                $userRoles = $user->roles->pluck('name')->toArray();
                
                // Admin and Super Admin see all presence records
                if (in_array('admin', $userRoles) || in_array('super_admin', $userRoles)) {
                    return $query;
                }
                
                // Manager sees presence records only in their region
                if (in_array('manager', $userRoles)) {
                    $managerRegions = $user->regions->pluck('id')->toArray();
                    if (!empty($managerRegions)) {
                        return $query->whereHas('workplace', function (Builder $q) use ($managerRegions) {
                            $q->whereIn('region_id', $managerRegions);
                        });
                    }
                }
                
                // Supervisor sees presence records only for their workplaces
                if (in_array('supervisor', $userRoles)) {
                    $supervisorWorkplaces = $user->workPlaces->pluck('id')->toArray();
                    if (!empty($supervisorWorkplaces)) {
                        return $query->whereIn('work_place_id', $supervisorWorkplaces);
                    }
                }
                
                // Default: no access for other roles
                return $query->whereRaw('1 = 0');
            })
            ->defaultSort('date', 'desc')
            ->paginated([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\MainPresenceSelection::route('/'),
            'monthly' => Pages\MonthlyPresence::route('/monthly/{workplace}/{date?}'),
            'worker-add' => Pages\AddMonthlyWorker::route('/monthly/{workplace}/{date}/workers/add'),
            'configure-hours' => Pages\ConfigureMonthlyHours::route('/monthly/{workplace}/{date}/configure-hours'),
            'records' => Pages\ListPresenceRecords::route('/records'),
            'create' => Pages\CreatePresenceRecord::route('/create'),
            'view' => Pages\ViewPresenceRecord::route('/{record}'),
            'edit' => Pages\EditPresenceRecord::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            PresenceStatsOverview::class,
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', WorkerRecord::WORKER_RECORD_WAITING)->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['worker.name', 'worker.family_name', 'workplace.name'];
    }

    public static function getGlobalSearchResultTitle($record): string
    {
        return $record->worker->name . ' ' . $record->worker->family_name;
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'Работно място' => $record->workplace?->name,
            'Дата' => $record->date?->format('d.m.Y'),
            'Часове' => $record->hours . ' ч.',
        ];
    }

    public static function getPermissionPrefixes(): array
    {
        return [
            "view",
            "view_any",
            "create",
            "update",
            "delete",
            "delete_any",
        ];
    }
}
