<?php

namespace App\Filament\Service\Resources;

use App\Filament\Service\Resources\WorkPlaceResource\Pages;
use App\Filament\Service\Resources\WorkPlaceResource\RelationManagers;
use App\Filament\Service\Resources\WorkPlaceResource\Widgets\WorkPlaceStatsOverview;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Viki\Service\Models\Elequent\WorkPlace;
use Viki\Service\Models\Elequent\Region;
use Viki\Service\Models\Elequent\Client;
class WorkPlaceResource extends Resource
{
    protected static ?string $model = WorkPlace::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationLabel = 'Обекти';

    protected static ?string $modelLabel = 'Обект';

    protected static ?string $pluralModelLabel = 'Обекти';

    protected static ?string $navigationGroup = '🏢 Организация';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('🏢 Основни данни')
                    ->description('Основна информация за работния обект')
                    ->schema([
                        TextInput::make('name')
                            ->label('Име на обекта')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        Textarea::make('address')
                            ->label('Адрес')
                            ->maxLength(500)
                            ->rows(3)
                            ->columnSpan(2),
                    ])
                    ->columns(2),

                Section::make('💰 Бюджет и финанси')
                    ->description('Финансова информация и бюджетни ограничения')
                    ->schema([
                        TextInput::make('budget')
                            ->label('Основен бюджет (лв.)')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix('лв.')
                            ->helperText('Основен месечен бюджет за обекта')
                            ->columnSpan(1),
                    ])
                    ->columns(1),

                Section::make('🗺️ Организация')
                    ->description('Връзка с регион и клиент')
                    ->schema([
                        Select::make('region_id')
                            ->label('Регион')
                            ->options(Region::where('status', Region::REGION_ACTIVE)->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpan(1),

                        Select::make('client_id')
                            ->label('Клиент')
                            ->options(Client::where('status', Client::CLIENT_ACTIVE)->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Section::make('⚙️ Настройки')
                    ->description('Статус и конфигурация')
                    ->schema([
                        Select::make('status')
                            ->label('Статус')
                            ->options([
                                WorkPlace::WORK_PLACE_ACTIVE => 'Активен',
                                WorkPlace::WORK_PLACE_UNACTIVE => 'Неактивен',
                            ])
                            ->required()
                            ->default(WorkPlace::WORK_PLACE_ACTIVE)
                            ->columnSpan(1),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Име на обекта')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('address')
                    ->label('Адрес')
                    ->searchable()
                    ->sortable()
                    ->limit(40)
                    ->wrap(),

                TextColumn::make('region.name')
                    ->label('Регион')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('client.name')
                    ->label('Клиент')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('budget')
                    ->label('Бюджет')
                    ->money('BGN')
                    ->sortable(),

                TextColumn::make('workers_count')
                    ->label('Работници')
                    ->counts('workers')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                BadgeColumn::make('status')
                    ->label('Статус')
                    ->getStateUsing(fn (WorkPlace $record): string => 
                        $record->status === WorkPlace::WORK_PLACE_ACTIVE ? 'Активен' : 'Неактивен'
                    )
                    ->colors([
                        'success' => static fn ($state): bool => $state === 'Активен',
                        'danger' => static fn ($state): bool => $state === 'Неактивен',
                    ]),

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
                        WorkPlace::WORK_PLACE_ACTIVE => 'Активен',
                        WorkPlace::WORK_PLACE_UNACTIVE => 'Неактивен',
                    ]),

                SelectFilter::make('region_id')
                    ->label('Регион')
                    ->relationship('region', 'name'),

                SelectFilter::make('client_id')
                    ->label('Клиент')
                    ->relationship('client', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Преглед'),

                Tables\Actions\EditAction::make()
                    ->label('Редактиране'),

                Action::make('view_workers')
                    ->label('Работници')
                    ->icon('heroicon-o-users')
                    ->color('info')
                    ->url(fn (WorkPlace $record): string => "/service/workers?tableFilters[work_place_id][value]={$record->id}")
                    ->visible(fn (WorkPlace $record): bool => $record->workers_count > 0),

                Action::make('view_region')
                    ->label('Регион')
                    ->icon('heroicon-o-map')
                    ->color('info')
                    ->url(fn (WorkPlace $record): string => "/service/regions/{$record->region_id}")
                    ->visible(fn (WorkPlace $record): bool => $record->region_id !== null),

                Action::make('budget_history')
                    ->label('Бюджет история')
                    ->icon('heroicon-o-chart-bar')
                    ->color('warning')
                    ->url(fn (WorkPlace $record): string => route('service.workplace.budget-history', $record))
                    ->visible(fn (WorkPlace $record): bool => $record->monthlyBudget()->exists()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Изтриване'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ActivitiesRelationManager::class,
            RelationManagers\MonthlyBudgetsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkPlaces::route('/'),
            'create' => Pages\CreateWorkPlace::route('/create'),
            'view' => Pages\ViewWorkPlace::route('/{record}'),
            'edit' => Pages\EditWorkPlace::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            WorkPlaceStatsOverview::class,
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', WorkPlace::WORK_PLACE_ACTIVE)->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'info';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'address', 'region.name', 'client.name'];
    }

    public static function getGlobalSearchResultTitle($record): string
    {
        return $record->name;
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'Регион' => $record->region?->name,
            'Клиент' => $record->client?->name,
            'Бюджет' => number_format($record->budget, 2) . ' лв.',
        ];
    }
}
