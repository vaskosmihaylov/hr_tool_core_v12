<?php

namespace App\Filament\Service\Resources;

use App\Filament\Service\Resources\ClientResource\Pages;
use App\Filament\Service\Resources\ClientResource\RelationManagers;
use App\Filament\Service\Resources\ClientResource\Widgets\ClientStatsOverview;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Viki\Service\Models\Elequent\Client;
use Viki\Service\Models\Elequent\Region;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Клиенти';

    protected static ?string $modelLabel = 'Клиент';

    protected static ?string $pluralModelLabel = 'Клиенти';

    protected static ?string $navigationGroup = '🏢 Организация';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('🏢 Основни данни')
                    ->description('Основна информация за клиента')
                    ->schema([
                        TextInput::make('name')
                            ->label('Име на клиента')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->columnSpan(2),
                    ])
                    ->columns(2),

                Section::make('💰 Финансова информация')
                    ->description('Бюджет и финансови ограничения')
                    ->schema([
                        TextInput::make('budget')
                            ->label('Общ бюджет (лв.)')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix('лв.')
                            ->helperText('Общ бюджет за всички дейности на клиента')
                            ->columnSpan(1),
                    ])
                    ->columns(1),

                Section::make('⚙️ Настройки')
                    ->description('Статус и конфигурация')
                    ->schema([
                        Select::make('status')
                            ->label('Статус')
                            ->options([
                                Client::CLIENT_ACTIVE => 'Активен',
                                Client::CLIENT_UNACTIVE => 'Неактивен',
                            ])
                            ->required()
                            ->default(Client::CLIENT_ACTIVE)
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
                    ->label('Име на клиента')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('budget')
                    ->label('Бюджет')
                    ->money('BGN')
                    ->sortable(),

                TextColumn::make('workplaces_count')
                    ->label('Обекти')
                    ->counts('workplaces')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('active_workplaces_count')
                    ->label('Активни обекти')
                    ->getStateUsing(function (Client $record): int {
                        return $record->workplaces()->where('status', 0)->count();
                    })
                    ->badge()
                    ->color('success'),

                TextColumn::make('regions_count')
                    ->label('Региони')
                    ->counts('regions')
                    ->sortable()
                    ->badge()
                    ->color('warning'),

                BadgeColumn::make('status')
                    ->label('Статус')
                    ->getStateUsing(fn (Client $record): string => 
                        $record->status === Client::CLIENT_ACTIVE ? 'Активен' : 'Неактивен'
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
                        Client::CLIENT_ACTIVE => 'Активен',
                        Client::CLIENT_UNACTIVE => 'Неактивен',
                    ]),

                Tables\Filters\Filter::make('has_workplaces')
                    ->label('С обекти')
                    ->query(fn ($query) => $query->has('workplaces')),

                Tables\Filters\Filter::make('has_active_workplaces')
                    ->label('С активни обекти')
                    ->query(fn ($query) => $query->whereHas('workplaces', fn ($q) => $q->where('status', 0))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Преглед'),

                Tables\Actions\EditAction::make()
                    ->label('Редактиране'),

                Action::make('view_workplaces')
                    ->label('Обекти')
                    ->icon('heroicon-o-building-office')
                    ->color('info')
                    ->url(fn (Client $record): string => "/service/work-places?tableFilters[client_id][value]={$record->id}")
                    ->visible(fn (Client $record): bool => $record->workplaces_count > 0),

                Action::make('view_regions')
                    ->label('Региони')
                    ->icon('heroicon-o-map')
                    ->color('warning')
                    ->action(function (Client $record) {
                        $regions = $record->regions->pluck('name')->join(', ');
                        \Filament\Notifications\Notification::make()
                            ->title('Региони на клиента')
                            ->body($regions ?: 'Няма назначени региони')
                            ->info()
                            ->send();
                    })
                    ->visible(fn (Client $record): bool => $record->regions_count > 0),
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
            RelationManagers\WorkPlacesRelationManager::class,
            RelationManagers\RegionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'view' => Pages\ViewClient::route('/{record}'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            ClientStatsOverview::class,
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', Client::CLIENT_ACTIVE)->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }

    public static function getGlobalSearchResultTitle($record): string
    {
        return $record->name;
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'Бюджет' => number_format($record->budget, 2) . ' лв.',
            'Обекти' => $record->workplaces_count,
            'Статус' => $record->status === Client::CLIENT_ACTIVE ? 'Активен' : 'Неактивен',
        ];
    }
}
