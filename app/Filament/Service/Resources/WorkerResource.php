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
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Collection;

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

                    TextInput::make("position")
                        ->label("Длъжност")
                        ->maxLength(255)
                        ->nullable()
                        ->columnSpan(1)
                        ->visibleOn('create'),

                    TextInput::make("note")
                        ->label("Бележки")
                        ->maxLength(255)
                        ->nullable()
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
                        ->options(
                            fn(
                                Get $get
                            ): Collection => WorkPlaceActivity::query()
                                ->where("work_place_id", $get("work_place_id"))
                                ->whereNull("date")
                                ->get()
                                ->mapWithKeys(
                                    fn($activity) => [
                                        $activity->id => $activity->activity,
                                    ]
                                )
                        )
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
            ->defaultSort('name', 'asc')
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

                SelectFilter::make("region_id")
                    ->label("Регион")
                    ->relationship("region", "name"),

                SelectFilter::make("work_place_id")
                    ->label("Обект")
                    ->relationship("workplace", "name"),

                SelectFilter::make("type_working")
                    ->label("Тип работа")
                    ->options(
                        collect(Worker::workerTypeWorking())
                            ->pluck("name", "id")
                            ->toArray()
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label("Изтриване"),
                ]),
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
            ->defaultSort("created_at", "desc");
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
