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

class WorkerResource extends Resource
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
                        ->label("Длъжност/Бележки")
                        ->maxLength(255)
                        ->columnSpan(2),
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
                        ->label("")
                        ->options(
                            collect(Worker::workerStatuses())
                                ->pluck("name", "id")
                                ->toArray()
                        )
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
                    ->sortable(),

                TextColumn::make("middle_name")
                    ->label("Презиме")
                    ->searchable()
                    ->sortable(),

                TextColumn::make("family_name")
                    ->label("Фамилия")
                    ->searchable()
                    ->sortable(),

                TextColumn::make("egn")->label("ЕГН")->searchable()->sortable(),

                TextColumn::make("note")
                    ->label("Бележки")
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                TextColumn::make("region.name")->label("Регион")->sortable(),

                TextColumn::make("workplace.name")->label("Обект")->sortable(),

                TextColumn::make("workplaceActivity.activity")
                    ->label("Дейност")
                    ->sortable(),

                TextColumn::make("neto_salary")
                    ->label("Нето заплата")
                    ->money("BGN")
                    ->sortable(),

                TextColumn::make("hours_per_day")
                    ->label("Работно време")
                    ->suffix(" ч.")
                    ->sortable(),

                BadgeColumn::make("status")
                    ->label("Статус")
                    ->getStateUsing(function (Worker $record): string {
                        $statuses = collect(Worker::workerStatuses());
                        $status = $statuses->firstWhere("id", $record->status);
                        // Capitalize the first letter of the status name
                        return $status ? ucfirst($status["name"]) : "Неизвестен";
                    })
                    ->colors([
                        "success" => static fn($state): bool => $state === "Активен",
                        "danger" => static fn($state): bool => $state === "Неактивен",
                    ]),

                TextColumn::make("start_date")
                    ->label("Започнал на")
                    ->date("d.m.Y")
                    ->sortable(),

                TextColumn::make("created_at")
                    ->label("Създаден на")
                    ->dateTime("d.m.Y H:i")
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make("status")
                    ->label("Статус")
                    ->options(
                        collect(Worker::workerStatuses())
                            ->pluck("name", "id")
                            ->toArray()
                    ),

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
            ->actions([
                Tables\Actions\ViewAction::make()->label("Преглед"),

                Tables\Actions\EditAction::make()->label("Редактиране"),

                

                Action::make("view_region")
                    ->label("Регион")
                    ->icon("heroicon-o-map")
                    ->color("info")
                    ->url(
                        fn(
                            Worker $record
                        ): string => "/service/regions/{$record->region_id}"
                    )
                    ->visible(
                        fn(Worker $record): bool => $record->region_id !== null
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label("Изтриване"),
                ]),
            ])
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
}
