<?php

namespace App\Filament\Service\Resources\WorkerResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use viki\Service\Models\Elequent\WorkerBonus;

class BonusesRelationManager extends RelationManager
{
    protected static string $relationship = 'bonuses';

    protected static ?string $title = 'Бонуси и Глоби';

    protected static ?string $modelLabel = 'Бонус/Глоба';

    protected static ?string $pluralModelLabel = 'Бонуси и Глоби';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->label('Тип')
                    ->options([
                        'bonus' => 'Бонус',
                        'penalty' => 'Глоба',
                    ])
                    ->required(),

                Forms\Components\TextInput::make('amount')
                    ->label('Сума (лв.)')
                    ->required()
                    ->numeric()
                    ->step(0.01),

                Forms\Components\DatePicker::make('date')
                    ->label('Дата')
                    ->required()
                    ->default(now()),

                Forms\Components\Textarea::make('reason')
                    ->label('Причина')
                    ->required()
                    ->rows(3)
                    ->columnSpanFull(),

                Forms\Components\Select::make('status')
                    ->label('Статус')
                    ->options([
                        0 => 'Чакащ одобрение',
                        1 => 'Одобрен',
                        2 => 'Отказан',
                    ])
                    ->default(0)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reason')
            ->columns([
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Тип')
                    ->getStateUsing(function ($record) {
                        return match($record->type) {
                            'bonus' => 'Бонус',
                            'penalty' => 'Глоба',
                            default => 'Неизвестен'
                        };
                    })
                    ->colors([
                        'success' => 'Бонус',
                        'danger' => 'Глоба',
                    ]),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Сума')
                    ->money('BGN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('date')
                    ->label('Дата')
                    ->date('d.m.Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Причина')
                    ->limit(50),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Статус')
                    ->getStateUsing(function ($record) {
                        return match($record->status) {
                            0 => 'Чакащ',
                            1 => 'Одобрен',
                            2 => 'Отказан',
                            default => 'Неизвестен'
                        };
                    })
                    ->colors([
                        'warning' => 'Чакащ',
                        'success' => 'Одобрен',
                        'danger' => 'Отказан',
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Създаден на')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Тип')
                    ->options([
                        'bonus' => 'Бонус',
                        'penalty' => 'Глоба',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        0 => 'Чакащ одобрение',
                        1 => 'Одобрен',
                        2 => 'Отказан',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Нов бонус/глоба'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Редактиране'),
                Tables\Actions\DeleteAction::make()
                    ->label('Изтриване'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Изтриване'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
