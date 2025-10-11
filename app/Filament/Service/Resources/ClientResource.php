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
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Viki\Service\Models\Elequent\Client;
use Viki\Service\Models\Elequent\Region;
use viki\Service\Models\Elequent\Worker;

class ClientResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Client::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Клиенти';

    protected static ?string $modelLabel = 'Клиент';

    protected static ?string $pluralModelLabel = 'Клиенти';

    protected static ?string $navigationGroup = '🏢 Организация';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        
        if (!$user) {
            return false;
        }
        
        // Supervisor cannot see clients in navigation - they should only see Objects
        if ($user->hasRole('supervisor')) {
            return false;
        }
        
        // Admin, Super Admin, and Manager can see clients
        return $user->hasAnyRole(['admin', 'super_admin', 'manager']);
    }

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
                    ->label('Клиент')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->size('lg')
                    ->wrap(),

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
                    ->color('primary'),



                TextColumn::make('created_at')
                    ->label('Създаден')
                    ->date('d.m.Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('name', 'asc')
            ->persistSortInSession()
            ->persistSearchInSession()
            ->striped()
            ->paginated([10, 25, 50, 100])
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

            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Изтриване'),
                ]),
            ])
            ->modifyQueryUsing(function (\Illuminate\Database\Eloquent\Builder $query) {
                $user = auth()->user();
                
                if (!$user) {
                    return $query->whereRaw('1 = 0');
                }
                
                $userRoles = $user->roles->pluck('name')->toArray();
                
                // Admin and Super Admin see all clients
                if (in_array('admin', $userRoles) || in_array('super_admin', $userRoles)) {
                    return $query;
                }
                
                // Manager sees clients only in their region through workplaces
                if (in_array('manager', $userRoles)) {
                    $managerRegions = $user->regions->pluck('id')->toArray();
                    if (!empty($managerRegions)) {
                        return $query->whereHas('workplaces', function (\Illuminate\Database\Eloquent\Builder $q) use ($managerRegions) {
                            $q->whereIn('region_id', $managerRegions);
                        });
                    }
                }
                
                // Supervisor sees clients only through their workplaces
                if (in_array('supervisor', $userRoles)) {
                    $supervisorWorkplaces = $user->workPlaces->pluck('id')->toArray();
                    if (!empty($supervisorWorkplaces)) {
                        return $query->whereHas('workplaces', function (\Illuminate\Database\Eloquent\Builder $q) use ($supervisorWorkplaces) {
                            $q->whereIn('viki_work_place.id', $supervisorWorkplaces);
                        });
                    }
                }
                
                // Default: no access for other roles
                return $query->whereRaw('1 = 0');
            })
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
