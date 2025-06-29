<?php

namespace App\Filament\Service\Resources\WorkerResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use viki\Service\Models\Elequent\Vacation;

class VacationsRelationManager extends RelationManager
{
    protected static string $relationship = 'vacations';

    protected static ?string $title = 'Отпуски';

    protected static ?string $modelLabel = 'Отпуска';

    protected static ?string $pluralModelLabel = 'Отпуски';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('date_start')
                    ->label('Начална дата')
                    ->required(),

                Forms\Components\DatePicker::make('date_end')
                    ->label('Крайна дата')
                    ->required()
                    ->after('date_start'),

                Forms\Components\Textarea::make('reason')
                    ->label('Причина')
                    ->rows(3)
                    ->columnSpanFull(),

                Forms\Components\Select::make('status')
                    ->label('Статус')
                    ->options([
                        0 => 'Чакаща одобрение',
                        1 => 'Одобрена',
                        2 => 'Отказана',
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
                Tables\Columns\TextColumn::make('date_start')
                    ->label('Начална дата')
                    ->date('d.m.Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('date_end')
                    ->label('Крайна дата')
                    ->date('d.m.Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('days_count')
                    ->label('Дни')
                    ->getStateUsing(function ($record) {
                        $start = \Carbon\Carbon::parse($record->date_start);
                        $end = \Carbon\Carbon::parse($record->date_end);
                        return $start->diffInDays($end) + 1;
                    })
                    ->suffix(' дни'),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Причина')
                    ->limit(50),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Статус')
                    ->getStateUsing(function ($record) {
                        return match($record->status) {
                            0 => 'Чакаща',
                            1 => 'Одобрена',
                            2 => 'Отказана',
                            default => 'Неизвестен'
                        };
                    })
                    ->colors([
                        'warning' => 'Чакаща',
                        'success' => 'Одобрена',
                        'danger' => 'Отказана',
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Заявена на')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        0 => 'Чакаща одобрение',
                        1 => 'Одобрена',
                        2 => 'Отказана',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Нова отпуска'),
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
