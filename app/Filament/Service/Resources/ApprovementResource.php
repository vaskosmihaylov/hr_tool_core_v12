<?php

namespace App\Filament\Service\Resources;

use App\Filament\Service\Resources\ApprovementResource\Pages;
use App\Services\Approvement\ApprovementActionService;
use viki\Service\Models\Elequent\Approvement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
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
            ->actionsColumnLabel('Опции')
            ->recordUrl(null)
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->iconButton()
                    ->icon('heroicon-s-hand-thumb-up')
                    ->tooltip('Одобри')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Одобри заявката')
                    ->modalDescription('Сигурни ли сте, че искате да одобрите тази заявка?')
                    ->action(function (Approvement $record) {
                        app(ApprovementActionService::class)->approve($record);

                        Notification::make()
                            ->success()
                            ->title('Искането е одобрено')
                            ->body('Статусът беше променен на "одобрен".')
                            ->send();
                    })
                    ->visible(fn (Approvement $record): bool =>
                        $record->status === Approvement::STATUS_NEW
                        && static::canManageApprovementActions()
                    ),

                Tables\Actions\Action::make('disapprove')
                    ->iconButton()
                    ->icon('heroicon-s-hand-thumb-down')
                    ->tooltip('Неодобрявай')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Неодобри заявката')
                    ->modalDescription('Сигурни ли сте, че искате да неодобрите тази заявка?')
                    ->action(function (Approvement $record) {
                        app(ApprovementActionService::class)->disapprove($record);

                        Notification::make()
                            ->success()
                            ->title('Искането е неодобрено')
                            ->body('Статусът беше променен на "неодобрен".')
                            ->send();
                    })
                    ->visible(fn (Approvement $record): bool =>
                        $record->status === Approvement::STATUS_NEW
                        && static::canManageApprovementActions()
                    ),

                Tables\Actions\Action::make('comment')
                    ->iconButton()
                    ->icon('heroicon-s-chat-bubble-left-ellipsis')
                    ->tooltip('Добави коментар')
                    ->color('primary')
                    ->form([
                        Forms\Components\Textarea::make('comment')
                            ->label('Коментар')
                            ->required()
                            ->maxLength(1000)
                            ->rows(4),
                    ])
                    ->modalHeading('Добавяне на коментар')
                    ->modalSubmitActionLabel('Добави')
                    ->action(function (Approvement $record, array $data) {
                        $comment = trim((string) ($data['comment'] ?? ''));

                        if ($comment === '') {
                            Notification::make()
                                ->warning()
                                ->title('Коментарът е задължителен')
                                ->body('Моля, въведете съдържание на коментара.')
                                ->send();

                            return;
                        }

                        app(ApprovementActionService::class)->addComment($record, $comment);

                        Notification::make()
                            ->success()
                            ->title('Коментар добавен')
                            ->body('Коментарът беше записан успешно.')
                            ->send();
                    })
                    ->visible(fn (): bool => static::canCommentOnApprovement()),
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

    protected static function canManageApprovementActions(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin', 'manager', 'super_admin'])) {
            return true;
        }

        if (method_exists($user, 'hasPermissionUrl')) {
            if ($user->hasPermissionUrl('/service/approvement/approve') || $user->hasPermissionUrl('/service/approvement/disapprove')) {
                return true;
            }
        }

        return false;
    }

    protected static function canCommentOnApprovement(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if (method_exists($user, 'hasPermissionUrl') && $user->hasPermissionUrl('/service/approvement/comment/')) {
            return true;
        }

        return false;
    }
}
