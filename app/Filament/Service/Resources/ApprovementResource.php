<?php

namespace App\Filament\Service\Resources;

use App\Filament\Service\Resources\ApprovementResource\Pages;
use Viki\Service\Models\Elequent\Approvement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ApprovementResource extends Resource
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
                    ->required()
                    ->searchable()
                    ->preload(),
                    
                Forms\Components\DatePicker::make('date')
                    ->label('Дата')
                    ->required()
                    ->default(now()),
                    
                Forms\Components\TextInput::make('sum_above_budget')
                    ->label('Надвишение бюджет')
                    ->numeric()
                    ->prefix('BGN'),
                    
                Forms\Components\Select::make('type_id')
                    ->label('Клиент надвишен')
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
            ->defaultSort('date', 'desc');
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
            'create' => Pages\CreateApprovement::route('/create'),

            'edit' => Pages\EditApprovement::route('/{record}/edit'),
        ];
    }
}
