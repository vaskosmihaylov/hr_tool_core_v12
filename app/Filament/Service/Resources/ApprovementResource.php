<?php

namespace App\Filament\Service\Resources;

use App\Filament\Service\Resources\ApprovementResource\Pages;
use Viki\Service\Models\Elequent\Approvement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ApprovementResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Approvement::class;

    protected static ?string $navigationIcon = 'heroicon-o-check-circle';
    
    protected static ?string $navigationLabel = 'Одобрения';
    
    protected static ?string $modelLabel = 'одобрение';
    
    protected static ?string $pluralModelLabel = 'одобрения';
    
    protected static ?string $navigationGroup = '👥 Човешки ресурси';
    
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('work_place_id')
                    ->label('Обект')
                    ->relationship('workplace', 'name')
                    ->disabled(fn ($record) => $record && $record->exists) // Read-only for auto-generated
                    ->required()
                    ->searchable()
                    ->preload(),
                    
                Forms\Components\DatePicker::make('date')
                    ->label('Дата')
                    ->disabled(fn ($record) => $record && $record->exists) // Read-only for auto-generated
                    ->required()
                    ->default(now()),
                    
                Forms\Components\TextInput::make('sum_above_budget')
                    ->label('Надвишение бюджет')
                    ->disabled(fn ($record) => $record && $record->exists) // Read-only for auto-generated
                    ->numeric()
                    ->prefix('BGN'),
                    
                Forms\Components\Select::make('type_id')
                    ->label('Клиент надвишен')
                    ->disabled(fn ($record) => $record && $record->exists) // Read-only for auto-generated
                    ->options([
                        0 => 'Заместване',
                        1 => 'Не',
                        2 => 'Да', 
                        3 => 'Бонус на работник',
                    ])
                    ->required(),
                    
                Forms\Components\Select::make('status')
                    ->label('Статус')
                    ->options([
                        0 => 'Нов',
                        1 => 'Одобрен',
                        2 => 'Неодобрен',
                    ])
                    ->default(0)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('workplace.name')
                    ->label('Обект')
                    ->sortable()
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('date')
                    ->label('Създадено на')
                    ->date('d.m.Y')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('date')
                    ->label('За месец')
                    ->date('d.m.Y')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('sum_above_budget')
                    ->label('Надвишение бюджет')
                    ->money('BGN')
                    ->sortable(),
                    
                Tables\Columns\BadgeColumn::make('type_id')
                    ->label('Клиент надвишен')
                    ->formatStateUsing(fn ($state): string => match ((string)$state) {
                        '0' => 'заместване',
                        '1' => 'не',
                        '2' => 'да',
                        default => 'неизвестно',
                    })
                    ->colors([
                        'secondary' => 0,
                        'success' => 1, 
                        'warning' => 2,
                    ])
                    ->visible(fn ($record): bool => in_array($record->type_id, [0, 1, 2])),
                    
                Tables\Columns\BadgeColumn::make('type_id')
                    ->label('Бонус на работник')
                    ->formatStateUsing(fn ($state): string => $state == 3 ? 'да' : 'не')
                    ->colors([
                        'success' => fn ($state): bool => $state == 3,
                        'secondary' => fn ($state): bool => $state != 3,
                    ]),
                    
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Статус')
                    ->formatStateUsing(fn ($state): string => match ((string)$state) {
                        '0' => 'нов',
                        '1' => 'одобрен', 
                        '2' => 'неодобрен',
                        default => 'неизвестно',
                    })
                    ->colors([
                        'primary' => 0,
                        'success' => 1,
                        'danger' => 2,
                    ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('work_place_id')
                    ->label('Обект')
                    ->relationship('workplace', 'name')
                    ->searchable()
                    ->preload(),
                    
                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        '0' => 'Нов',
                        '1' => 'Одобрен',
                        '2' => 'Неодобрен',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Одобри')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->action(function (Approvement $record) {
                        $record->update(['status' => 1]);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Одобри заявката')
                    ->modalDescription('Сигурни ли сте, че искате да одобрите тази заявка?')
                    ->visible(fn (Approvement $record): bool => 
                        $record->status == 0 && (auth()->user()->hasRole('admin') || auth()->user()->hasRole('manager'))
                    ),
                    
                Tables\Actions\Action::make('disapprove')
                    ->label('Неодобри')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->action(function (Approvement $record) {
                        $record->update(['status' => 2]);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Неодобри заявката')
                    ->modalDescription('Сигурни ли сте, че искате да неодобрите тази заявка?')
                    ->visible(fn (Approvement $record): bool => 
                        $record->status == 0 && (auth()->user()->hasRole('admin') || auth()->user()->hasRole('manager'))
                    ),
                    
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                $user = auth()->user();
                
                if (!$user) {
                    return $query->whereRaw('1 = 0');
                }
                
                $userRoles = $user->roles->pluck('name')->toArray();
                
                // Admin and Super Admin see all approvements
                if (in_array('admin', $userRoles) || in_array('super_admin', $userRoles)) {
                    return $query->orderBy('status', 'asc')->orderBy('created_at', 'desc');
                }
                
                // Manager sees approvements only in their region
                if (in_array('manager', $userRoles)) {
                    $managerRegions = $user->regions->pluck('id')->toArray();
                    if (!empty($managerRegions)) {
                        return $query->whereHas('workplace', function (Builder $q) use ($managerRegions) {
                            $q->whereIn('region_id', $managerRegions);
                        })->orderBy('status', 'asc')->orderBy('created_at', 'desc');
                    }
                }
                
                // Supervisor sees approvements only for their workplaces
                if (in_array('supervisor', $userRoles)) {
                    $supervisorWorkplaces = $user->workPlaces->pluck('id')->toArray();
                    if (!empty($supervisorWorkplaces)) {
                        return $query->whereIn('work_place_id', $supervisorWorkplaces)
                            ->orderBy('status', 'asc')->orderBy('created_at', 'desc');
                    }
                }
                
                // Default: no access for other roles
                return $query->whereRaw('1 = 0');
            })
            ->defaultSort('status', 'asc'); // "Нов" (0) appears first, then by newest created_at
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApprovements::route('/'),
            // 'create' => Pages\CreateApprovement::route('/create'), // Removed - auto-generated only
            'edit' => Pages\EditApprovement::route('/{record}/edit'),
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
