<?php

namespace App\Filament\Service\Resources;

use App\Filament\Service\Resources\WorkerResource\Pages;
use App\Filament\Service\Resources\WorkerResource\RelationManagers;
use viki\Service\Models\Elequent\Worker;
use viki\Service\Models\Elequent\Region;
use viki\Service\Models\Elequent\WorkPlace;
use viki\Service\Models\Elequent\WorkPlaceActivity;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;

class WorkerResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Worker::class;

    protected static ?string $navigationIcon = "heroicon-o-users";

    protected static ?string $navigationLabel = "Работници";

    protected static ?string $modelLabel = "Работник";

    protected static ?string $pluralModelLabel = "Работници";

    protected static ?string $navigationGroup = "👥 Човешки ресурси";

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make("📋 Лични данни")
                ->description("Основна информация за служителя")
                ->schema([
                    TextInput::make("name")
                        ->label("Име")
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(1),

                    TextInput::make("middle_name")
                        ->label("Презиме")
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(1),

                    TextInput::make("family_name")
                        ->label("Фамилия")
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(1),

                    TextInput::make("egn")
                        ->label("ЕГН")
                        ->required()
                        ->length(10)
                        ->unique(ignoreRecord: true)
                        ->rules(['regex:/^[0-9]{10}$/'])
                        ->helperText("Въведете 10 цифри")
                        ->columnSpan(1),

                    TextInput::make("note")
                        ->label("Бележки")
                        ->maxLength(255)
                        ->nullable()
                        ->default('')
                        ->dehydrateStateUsing(fn ($state) => $state ?? '')
                        ->columnSpan(1),
                ])
                ->columns(2),

            Section::make("💼 Служебни данни")
                ->description("Информация за работното място и заплащане")
                ->schema([
                    DatePicker::make("start_date")
                        ->label("Дата на започване")
                        ->required()
                        ->columnSpan(1),

                    DatePicker::make("unactive_from_date")
                        ->label("Дата на приключване")
                        ->helperText("Оставете празно за безсрочен договор")
                        ->columnSpan(1),

                    TextInput::make("neto_salary")
                        ->label("Нето заплата (лв.)")
                        ->required()
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->columnSpan(1),

                    TextInput::make("hours_per_day")
                        ->label("Работно време (часове)")
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(24)
                        ->default(8)
                        ->columnSpan(1),
                ])
                ->columns(2),

            Section::make("🏢 Месторабота")
                ->description("Регион, обект и дейност")
                ->schema([
                    Select::make("region_id")
                        ->label("Регион")
                        ->options(
                            Region::where(
                                "status",
                                Region::REGION_ACTIVE
                            )->pluck("name", "id")
                        )
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set) {
                            $set("work_place_id", null);
                            $set("work_place_activity_id", null);
                        })
                        ->columnSpan(1),

                    Select::make("work_place_id")
                        ->label("Обект")
                        ->options(
                            fn(Get $get): Collection => WorkPlace::query()
                                ->where("region_id", $get("region_id"))
                                ->where("status", WorkPlace::WORK_PLACE_ACTIVE)
                                ->orderBy("name")
                                ->pluck("name", "id")
                        )
                        ->required()
                        ->live()
                        ->afterStateUpdated(
                            fn(Set $set) => $set("work_place_activity_id", null)
                        )
                        ->columnSpan(1),

                    Select::make("work_place_activity_id")
                        ->label("Дейност")
                        ->required()
                        ->searchable()
                        ->options(function (Get $get): Collection {
                            $workplaceId = $get("work_place_id");
                            if (!$workplaceId) {
                                return collect();
                            }

                            $options = WorkPlaceActivity::query()
                                ->where("work_place_id", $workplaceId)
                                ->orderByRaw('CASE WHEN date IS NULL THEN 0 ELSE 1 END')
                                ->orderBy('activity')
                                ->pluck('activity', 'id');

                            $current = $get('work_place_activity_id');
                            if ($current && !$options->has($current)) {
                                if ($activity = WorkPlaceActivity::find($current)) {
                                    $options->put($activity->id, $activity->activity);
                                }
                            }

                            return $options;
                        })
                        ->columnSpan(2),
                ])
                ->columns(2),

            Section::make("⚙️ Настройки")
                ->description("Статус и тип работа")
                ->schema([
                    Select::make("status")
                        ->label("Статус")
                        ->options([
                            0 => 'Активен',
                            1 => 'Неактивен',
                        ])
                        ->required()
                        ->default(Worker::WORKER_ACTIVE)
                        ->columnSpan(1),

                    Select::make("type_working")
                        ->label("Тип работа")
                        ->options(
                            collect(Worker::workerTypeWorking())
                                ->pluck("name", "id")
                                ->toArray()
                        )
                        ->required()
                        ->default(1)
                        ->columnSpan(1),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make("name")
                    ->label("Име")
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->wrap(),

                TextColumn::make("middle_name")
                    ->label("Презиме")
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->wrap(),

                TextColumn::make("family_name")
                    ->label("Фамилия")
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->wrap(),

                TextColumn::make("egn")
                    ->label("ЕГН")
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->copyable(),

                TextColumn::make("region.name")
                    ->label("Регион")
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make("workplace.name")
                    ->label("Обект")
                    ->sortable()
                    ->limit(25)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 25 ? $state : null;
                    }),

                TextColumn::make("workplaceActivity.activity")
                    ->label("Дейност")
                    ->sortable()
                    ->limit(30)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 30 ? $state : null;
                    }),

                TextColumn::make("neto_salary")
                    ->label("Заплата")
                    ->money("BGN", locale: 'bg')
                    ->sortable()
                    ->alignRight(),

                TextColumn::make("type_working")
                    ->label("Работно време")
                    ->getStateUsing(function ($record) {
                        return $record->type_working == Worker::WORKING_STANDART ? 'стандартно' : 'сумарно';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'стандартно' => 'success',
                        'сумарно' => 'info',
                        default => 'gray',
                    })
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make("hours_per_day")
                    ->label("Раб. време договор(h)")
                    ->suffix(" ч.")
                    ->sortable()
                    ->alignCenter(),

                BadgeColumn::make("status")
                    ->label("Статус")
                    ->formatStateUsing(fn (int|string|null $state): string => (int) $state === Worker::WORKER_ACTIVE ? 'Активен' : 'Неактивен')
                    ->color(fn (int|string|null $state): string => (int) $state === Worker::WORKER_ACTIVE ? 'success' : 'danger')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make("note")
                    ->label("Бележки")
                    ->searchable()
                    ->limit(40)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 40 ? $state : null;
                    })
                    ->toggleable(),

                TextColumn::make("created_at")
                    ->label("Създаден")
                    ->dateTime("d.m.Y")
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->persistSortInSession()
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->striped()
            ->paginated([10, 25, 50, 100, 'all'])
            ->filters([
                SelectFilter::make("status")
                    ->label("Статус")
                    ->options([
                        0 => 'Активен',
                        1 => 'Неактивен',
                    ]),

                Filter::make('location_filters')
                    ->form([
                        Select::make('region_id')
                            ->label('Регион')
                            ->options(fn (): array => Region::query()
                                ->where("status", Region::REGION_ACTIVE)
                                ->orderBy("name")
                                ->pluck("name", "id")
                                ->toArray()
                            )
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('work_place_id', null)),

                        Select::make('work_place_id')
                            ->label('Обект')
                            ->options(function (Get $get): array {
                                $regionId = $get('region_id');

                                $query = WorkPlace::query()
                                    ->where("status", WorkPlace::WORK_PLACE_ACTIVE)
                                    ->orderBy("name");

                                // Filter by region if one is selected
                                if ($regionId !== null && $regionId !== '') {
                                    $query->where("region_id", (int) $regionId);
                                } else {
                                    // Show all workplaces from active regions
                                    $query->whereHas("region", fn (Builder $regionQuery) => $regionQuery
                                        ->where("status", Region::REGION_ACTIVE)
                                    );
                                }

                                return $query->pluck("name", "id")->toArray();
                            })
                            ->searchable()
                            ->native(false)
                            ->live(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['region_id'] ?? null,
                                fn (Builder $query, $regionId): Builder => $query->where('region_id', $regionId)
                            )
                            ->when(
                                $data['work_place_id'] ?? null,
                                fn (Builder $query, $workPlaceId): Builder => $query
                                    ->where("work_place_id", $workPlaceId)
                                    ->whereHas("workplace", function (Builder $workplaceQuery) {
                                        $workplaceQuery
                                            ->where("status", WorkPlace::WORK_PLACE_ACTIVE)
                                            ->whereHas("region", fn (Builder $regionQuery) => $regionQuery
                                                ->where("status", Region::REGION_ACTIVE)
                                            );
                                    })
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['region_id'] ?? null) {
                            $region = Region::find($data['region_id']);
                            if ($region) {
                                $indicators['region_id'] = 'Регион: ' . $region->name;
                            }
                        }

                        if ($data['work_place_id'] ?? null) {
                            $workplace = WorkPlace::find($data['work_place_id']);
                            if ($workplace) {
                                $indicators['work_place_id'] = 'Обект: ' . $workplace->name;
                            }
                        }

                        return $indicators;
                    }),

                SelectFilter::make("type_working")
                    ->label("Тип работа")
                    ->options(
                        collect(Worker::workerTypeWorking())
                            ->pluck("name", "id")
                            ->toArray()
                    ),
            ])
            ->actions([])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label("Изтриване")
                        ->visible(fn (): bool => static::canDeleteWorkers()),
                ])->visible(fn (): bool => static::canDeleteWorkers()),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                $user = auth()->user();
                
                if (!$user) {
                    return $query->whereRaw('1 = 0');
                }
                
                $userRoles = $user->roles->pluck('name')->toArray();
                
                // Admin and Super Admin see all workers
                if (in_array('admin', $userRoles) || in_array('super_admin', $userRoles)) {
                    return $query;
                }
                
                // Manager sees workers only in their region
                if (in_array('manager', $userRoles)) {
                    $managerRegions = $user->regions->pluck('id')->toArray();
                    if (!empty($managerRegions)) {
                        return $query->whereIn('region_id', $managerRegions);
                    }
                }
                
                // Supervisor sees workers only in their workplaces
                if (in_array('supervisor', $userRoles)) {
                    $supervisorWorkplaces = $user->workPlaces->pluck('id')->toArray();
                    if (!empty($supervisorWorkplaces)) {
                        return $query->whereIn('work_place_id', $supervisorWorkplaces);
                    }
                }
                
                // Default: no access for other roles
                return $query->whereRaw('1 = 0');
            })
            ->defaultSort("name", "asc");
    }

    public static function canDeleteWorkers(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'super_admin']) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return static::canDeleteWorkers();
    }

    public static function canDeleteAny(): bool
    {
        return static::canDeleteWorkers();
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\VacationsRelationManager::class,
            RelationManagers\BonusesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            "index" => Pages\ListWorkers::route("/"),
            "create" => Pages\CreateWorker::route("/create"),
            "view" => Pages\ViewWorker::route("/{record}"),
            "edit" => Pages\EditWorker::route("/{record}/edit"),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()
            ::where("status", Worker::WORKER_ACTIVE)
            ->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return "success";
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
