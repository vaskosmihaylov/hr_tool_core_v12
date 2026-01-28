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
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use viki\Service\Models\Elequent\WorkPlace;
use viki\Service\Models\Elequent\Region;
use viki\Service\Models\Elequent\Client;
use viki\Service\Models\Elequent\Worker;

class WorkPlaceResource extends Resource implements HasShieldPermissions
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
                            ->label('Основен бюджет (€)')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix('€')
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
                    ->label('Работно място')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->size('lg')
                    ->wrap(),

                TextColumn::make('address')
                    ->label('Адрес')
                    ->searchable()
                    ->sortable()
                    ->limit(40)
                    ->wrap(),

                TextColumn::make('region.name')
                    ->label('Регион')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('client.name')
                    ->label('Клиент')
                    ->sortable()
                    ->searchable()
                    ->limit(30),

                TextColumn::make('budget')
                    ->label('Бюджет')
                    ->money('EUR')
                    ->sortable(),

                TextColumn::make('workers_count')
                    ->label('Работници')
                    ->counts('workers')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('active_workers_count')
                    ->label('Активни работници')
                    ->getStateUsing(function (WorkPlace $record): int {
                        return $record->workers()->where('status', 0)->count();
                    })
                    ->badge()
                    ->color('success'),



                TextColumn::make('created_at')
                    ->label('Създаден')
                    ->date('d.m.Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->striped()
            ->paginated([10, 25, 50, 100])
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

            ->actionsColumnLabel('Опции')
            ->actions([
                Tables\Actions\EditAction::make()
                    ->iconButton()
                    ->icon('heroicon-s-pencil-square')
                    ->tooltip('Редакция')
                    ->hiddenLabel()
                    ->color('secondary')
                    ->url(fn (WorkPlace $record): string => static::getUrl('edit', ['record' => $record]))
                    ->visible(fn (WorkPlace $record): bool => static::canEdit($record)),

                Action::make('manageActivities')
                    ->iconButton()
                    ->icon('heroicon-s-briefcase')
                    ->tooltip('Дейности')
                    ->hiddenLabel()
                    ->color('success')
                    ->url(fn (WorkPlace $record): string => static::getUrl('activities', ['record' => $record]))
                    ->visible(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'super_admin', 'manager'])),

                DeleteAction::make()
                    ->iconButton()
                    ->icon('heroicon-s-trash')
                    ->tooltip('Изтриване')
                    ->hiddenLabel()
                    ->visible(fn (WorkPlace $record): bool => static::canDelete($record)),
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
                
                // Admin and Super Admin see all workplaces
                if (in_array('admin', $userRoles) || in_array('super_admin', $userRoles)) {
                    return $query;
                }
                
                // Manager sees workplaces only in their region
                if (in_array('manager', $userRoles)) {
                    $managerRegions = $user->regions->pluck('id')->toArray();
                    if (!empty($managerRegions)) {
                        return $query->whereIn('region_id', $managerRegions);
                    }
                }
                
                // Supervisor sees only their assigned workplaces
                if (in_array('supervisor', $userRoles)) {
                    $supervisorWorkplaces = $user->workPlaces->pluck('id')->toArray();
                    if (!empty($supervisorWorkplaces)) {
                        return $query->whereIn('id', $supervisorWorkplaces);
                    }
                }
                
                // Default: no access for other roles
                return $query->whereRaw('1 = 0');
            })
            ->defaultSort('name', 'asc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->orderBy('name');
    }

    public static function getRelations(): array
    {
        return [
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
            'activities' => Pages\ManageActivities::route('/{record}/activities'),
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
            'Бюджет' => number_format($record->budget, 2) . ' €',
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

    /**
     * Restrict WorkPlace creation for Supervisor role
     * Only admin, super_admin, and manager can create workplaces
     */
    public static function canCreate(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Supervisors cannot create workplaces
        if ($user->hasRole('supervisor')) {
            return false;
        }

        // Allow creation for admin, super_admin, and manager
        return $user->hasAnyRole(['admin', 'super_admin', 'manager']);
    }

    /**
     * Allow WorkPlace editing for all authenticated roles
     * Supervisors can edit workplaces, but not create or delete them
     */
    public static function canEdit($record): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // All authenticated users with proper roles can edit
        // This includes supervisors, managers, and admins
        return $user->hasAnyRole(['admin', 'super_admin', 'manager', 'supervisor']);
    }
}
