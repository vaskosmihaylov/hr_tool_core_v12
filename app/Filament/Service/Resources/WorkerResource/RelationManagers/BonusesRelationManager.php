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
                        0 => 'Бонус',
                        1 => 'Глоба',
                    ])
                    ->required()
                    ->default(0),

                Forms\Components\TextInput::make('sum')
                    ->label('Сума (лв.)')
                    ->required()
                    ->numeric()
                    ->step(0.01)
                    ->minValue(0),

                Forms\Components\Select::make('work_place_id')
                    ->label('Обект')
                    ->relationship('workplace', 'name')
                    ->required()
                    ->searchable(),

                Forms\Components\DatePicker::make('for_month')
                    ->label('За месец')
                    ->required()
                    ->default(now()->startOfMonth())
                    ->displayFormat('m/Y')
                    ->format('Y-m-d'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('sum')
            ->columns([
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Тип')
                    ->getStateUsing(function ($record) {
                        return match($record->type) {
                            0 => 'Бонус',
                            1 => 'Глоба',
                            default => 'Неизвестен'
                        };
                    })
                    ->colors([
                        'success' => 'Бонус',
                        'danger' => 'Глоба',
                    ]),

                Tables\Columns\TextColumn::make('sum')
                    ->label('Сума')
                    ->money('BGN', locale: 'bg')
                    ->sortable(),

                Tables\Columns\TextColumn::make('workplace.name')
                    ->label('Обект')
                    ->sortable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('for_month')
                    ->label('За месец')
                    ->date('m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Създаден на')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Тип')
                    ->options([
                        0 => 'Бонус',
                        1 => 'Глоба',
                    ]),

                Tables\Filters\SelectFilter::make('work_place_id')
                    ->label('Обект')
                    ->relationship('workplace', 'name'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Нов бонус/глоба')
                    ->mutateFormDataUsing(function (array $data): array {
                        // Ensure worker_id is set
                        $data['worker_id'] = $this->getOwnerRecord()->id;
                        
                        return $data;
                    }),
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
