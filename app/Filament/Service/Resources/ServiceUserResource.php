<?php

namespace App\Filament\Service\Resources;

use App\Filament\Service\Resources\ServiceUserResource\Pages;
use App\Models\User;
use Spatie\Permission\Models\Role;
use viki\Service\Models\Elequent\Region;
use viki\Service\Models\Elequent\WorkPlace;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Filament\Forms\Components\Section;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Forms\Get;
use Filament\Forms\Set;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;

class ServiceUserResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'users';

    protected static ?string $navigationIcon = 'heroicon-o-users';
    
    protected static ?string $navigationGroup = '🏢 Организация';
    
    protected static ?string $navigationLabel = 'Потребители';
    
    protected static ?string $modelLabel = 'потребител';
    
    protected static ?string $pluralModelLabel = 'потребители';
    
    protected static ?int $navigationSort = 10;

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Основна информация')
                    ->description('Лична информация за потребителя')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Име')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->label('Имейл')
                            ->email()
                            ->required()
                            ->unique(User::class, 'email', ignoreRecord: true)
                            ->maxLength(255),
                    ])->columns(2),
                    
                Section::make('Парола')
                    ->description('Парола за достъп до системата')
                    ->schema([
                        Forms\Components\TextInput::make('password')
                            ->label('Парола')
                            ->password()
                            ->required(fn (string $context): bool => $context === 'create')
                            ->minLength(8)
                            ->same('password_confirmation')
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->revealable(),
                        Forms\Components\TextInput::make('password_confirmation')
                            ->label('Потвърждение на парола')
                            ->password()
                            ->required(fn (string $context): bool => $context === 'create')
                            ->minLength(8)
                            ->dehydrated(false)
                            ->revealable(),
                    ])->columns(2)
                    ->visibleOn(['create', 'edit']),
                    
                Section::make('Роля и достъп')
                    ->description('Роля и права на потребителя')
                    ->schema([
                        Forms\Components\Select::make('user_role')
                            ->label('Роля')
                            ->options([
                                'manager' => 'Мениджър',
                                'supervisor' => 'Супервайзор',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('user_regions', []);
                                $set('user_workplaces', []);
                            }),

                        Forms\Components\Select::make('user_regions')
                            ->label('Регион')
                            ->options(fn () => static::getAvailableRegions())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('user_workplaces', []);
                            })
                            ->helperText('Изберете регионите, до които потребителят ще има достъп.')
                            ->required(fn (Get $get): bool => $get('user_role') === 'manager'),

                        Forms\Components\Select::make('user_workplaces')
                            ->label('Обекти')
                            ->options(function (Get $get) {
                                $selectedRegions = $get('user_regions');
                                return static::getFilteredWorkplaces($selectedRegions);
                            })
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText('Изберете обекти, до които потребителят ще има достъп.')
                            ->required(fn (Get $get): bool => $get('user_role') === 'supervisor'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Име')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('email')
                    ->label('Имейл')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                    
                Tables\Columns\BadgeColumn::make('roles.name')
                    ->label('Роля')
                    ->colors([
                        'danger' => 'admin',
                        'warning' => 'manager', 
                        'success' => 'supervisor',
                        'secondary' => 'hr',
                    ])
                    ->formatStateUsing(function ($state) {
                        return match($state) {
                            'admin' => 'Администратор',
                            'manager' => 'Мениджър',
                            'supervisor' => 'Супервайзор',
                            'hr' => 'HR специалист',
                            'super_admin' => 'Супер админ',
                            default => $state,
                        };
                    }),
                    
                Tables\Columns\TextColumn::make('regions.name')
                    ->label('Регион')
                    ->badge()
                    ->separator(', ')
                    ->placeholder('—'),
                    
                Tables\Columns\TextColumn::make('workPlaces.name')
                    ->label('Обекти')
                    ->badge()
                    ->separator(', ')
                    ->placeholder('—')
                    ->limit(2),
                    
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Статус')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->getStateUsing(fn (User $record): bool => !$record->deleted_at),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Създаден на')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->label('Роля')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),
                    
                Tables\Filters\SelectFilter::make('regions')
                    ->label('Регион')
                    ->relationship('regions', 'name')
                    ->multiple()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Редактиране')
                    ->visible(fn (): bool => static::canManageUsers()),
                Tables\Actions\DeleteAction::make()
                    ->label('Изтриване')
                    ->visible(fn (): bool => static::canDeleteUsers()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Изтриване на избраните')
                        ->visible(fn (): bool => static::canDeleteUsers()),
                ])
                ->visible(fn (): bool => static::canDeleteUsers()),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                return static::applyRoleBasedFiltering($query);
            });
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiceUsers::route('/'),
            'create' => Pages\CreateServiceUser::route('/create'),
            'edit' => Pages\EditServiceUser::route('/{record}/edit'),
        ];
    }

    /**
     * Get available regions based on current user's role
     */
    protected static function getAvailableRegions(): array
    {
        $currentUser = Auth::user();
        
        if (!$currentUser) {
            return [];
        }
        
        // Super Admin/Admin can see all regions
        if ($currentUser->hasAnyRole(['super_admin', 'admin'])) {
            return Region::pluck('name', 'id')->toArray();
        }
        
        // Manager can only assign users to their own region
        if ($currentUser->hasRole('manager')) {
            return $currentUser->regions()->pluck('name', 'id')->toArray();
        }
        
        return [];
    }

    /**
     * Get available workplaces based on current user's role
     * Note: This method is kept for backward compatibility but no longer used in form
     */
    protected static function getAvailableWorkplaces(): array
    {
        $currentUser = Auth::user();
        
        if (!$currentUser) {
            return [];
        }
        
        // Super Admin/Admin can see all workplaces (including inactive)
        if ($currentUser->hasAnyRole(['super_admin', 'admin'])) {
            return WorkPlace::orderBy('name')->pluck('name', 'id')->toArray();
        }
        
        // Manager can assign workplaces from their region (including inactive)
        if ($currentUser->hasRole('manager')) {
            $managerRegionIds = $currentUser->regions()->pluck('viki_regions.id');
            return WorkPlace::whereIn('region_id', $managerRegionIds)
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray();
        }
        
        // Supervisor can only assign their own workplaces (including inactive)
        if ($currentUser->hasRole('supervisor')) {
            return $currentUser->workPlaces()
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray();
        }
        
        return [];
    }

    /**
     * Get workplaces filtered by selected regions (used in form)
     */
    protected static function getFilteredWorkplaces(?array $selectedRegions = null): array
    {
        $currentUser = Auth::user();
        
        if (!$currentUser) {
            return [];
        }
        
        $query = WorkPlace::query();
        
        // If regions are selected in the form, filter by those regions
        if (!empty($selectedRegions)) {
            $query->whereIn('region_id', $selectedRegions);
        } else {
            // No regions selected - apply role-based filtering
            
            // Super Admin/Admin can see all workplaces
            if ($currentUser->hasAnyRole(['super_admin', 'admin'])) {
                // No additional filtering
            }
            // Manager can assign workplaces from their region
            elseif ($currentUser->hasRole('manager')) {
                $managerRegionIds = $currentUser->regions()->pluck('viki_regions.id');
                $query->whereIn('region_id', $managerRegionIds);
            }
            // Supervisor can only assign their own workplaces
            elseif ($currentUser->hasRole('supervisor')) {
                $supervisorWorkplaceIds = $currentUser->workPlaces()->pluck('viki_work_place.id');
                $query->whereIn('id', $supervisorWorkplaceIds);
            } else {
                // No access
                return [];
            }
        }
        
        // Include both active and inactive workplaces
        // Note: Removed status filter to show all workplaces including archived ones
        return $query->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Check if current user can manage users (create/edit/delete)
     */
    public static function canManageUsers(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'super_admin', 'manager']) ?? false;
    }

    public static function canDeleteUsers(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'super_admin']) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return static::canDeleteUsers();
    }

    public static function canDeleteAny(): bool
    {
        return static::canDeleteUsers();
    }

    /**
     * Apply role-based filtering to the query
     */
    protected static function applyRoleBasedFiltering(Builder $query): Builder
    {
        // Only show manager and supervisor users
        $query->whereHas('roles', function (Builder $q) {
            $q->whereIn('name', ['manager', 'supervisor']);
        });
        
        $user = Auth::user();
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }
        
        $userRoles = $user->roles->pluck('name')->toArray();
        
        // Admin and Super Admin see all manager/supervisor users
        if (in_array('admin', $userRoles) || in_array('super_admin', $userRoles)) {
            return $query;
        }
        
        // Manager sees users in their regions only
        if (in_array('manager', $userRoles)) {
            return static::applyManagerFiltering($query, $user);
        }
        
        // Supervisor can see users but with limited scope
        if (in_array('supervisor', $userRoles)) {
            return static::applySupervisorFiltering($query, $user);
        }
        
        // Default: no access for other roles
        return $query->whereRaw('1 = 0');
    }

    /**
     * Apply manager-specific filtering
     */
    protected static function applyManagerFiltering(Builder $query, User $user): Builder
    {
        $managerRegions = $user->regions->pluck('id')->toArray();
        
        if (empty($managerRegions)) {
            return $query->whereRaw('1 = 0');
        }
        
        return $query->where(function (Builder $q) use ($managerRegions) {
            // Show supervisors whose workplaces are in manager's regions
            $q->whereHas('workPlaces', function (Builder $workplace) use ($managerRegions) {
                $workplace->whereIn('viki_work_place.region_id', $managerRegions);
            })
            // OR show other managers in the same regions
            ->orWhereHas('regions', function (Builder $region) use ($managerRegions) {
                $region->whereIn('viki_regions.id', $managerRegions);
            });
        });
    }

    /**
     * Apply supervisor-specific filtering
     */
    protected static function applySupervisorFiltering(Builder $query, User $user): Builder
    {
        $supervisorWorkplaces = $user->workPlaces->pluck('id')->toArray();
        
        if (empty($supervisorWorkplaces)) {
            return $query->whereRaw('1 = 0');
        }
        
        // Get regions from supervisor's workplaces
        $regionIds = WorkPlace::whereIn('id', $supervisorWorkplaces)
            ->pluck('region_id')
            ->unique()
            ->toArray();
            
        if (empty($regionIds)) {
            return $query->whereRaw('1 = 0');
        }
        
        return $query->where(function (Builder $q) use ($regionIds) {
            $q->whereHas('regions', function (Builder $region) use ($regionIds) {
                $region->whereIn('viki_regions.id', $regionIds);
            })
            ->orWhereHas('workPlaces', function (Builder $workplace) use ($regionIds) {
                $workplace->whereIn('viki_work_place.region_id', $regionIds);
            });
        });
    }
}
