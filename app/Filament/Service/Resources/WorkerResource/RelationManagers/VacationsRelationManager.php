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
                Forms\Components\DatePicker::make('start_date')
                    ->label('Начална дата')
                    ->required(),

                Forms\Components\DatePicker::make('end_date')
                    ->label('Крайна дата')
                    ->required()
                    ->after('start_date'),

                Forms\Components\Select::make('type')
                    ->label('Тип отпуска')
                    ->options([
                        1 => 'платена отпуска',
                        2 => 'неплатена отпуска',
                        3 => 'болничен',
                    ])
                    ->required()
                    ->default(1),

                Forms\Components\Textarea::make('comment')
                    ->label('Коментар')
                    ->rows(3)
                    ->nullable()
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
            ->recordTitleAttribute('comment')
            ->columns([
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Начална дата')
                    ->date('d.m.Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('Крайна дата')
                    ->date('d.m.Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('day_count')
                    ->label('Дни')
                    ->getStateUsing(function ($record) {
                        if ($record->start_date && $record->end_date) {
                            $start = \Carbon\Carbon::parse($record->start_date);
                            $end = \Carbon\Carbon::parse($record->end_date);
                            return $start->diffInDays($end) + 1;
                        }
                        return $record->day_count ?? 0;
                    })
                    ->suffix(' дни'),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Тип отпуска')
                    ->getStateUsing(function ($record) {
                        return match($record->type) {
                            1 => 'платена отпуска',
                            2 => 'неплатена отпуска',
                            3 => 'болничен',
                            default => 'неизвестен'
                        };
                    })
                    ->colors([
                        'success' => 'платена отпуска',
                        'warning' => 'неплатена отпуска',
                        'danger' => 'болничен',
                    ]),

                Tables\Columns\TextColumn::make('comment')
                    ->label('Коментар')
                    ->limit(50)
                    ->placeholder('Няма коментар'),

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
                
                Tables\Filters\SelectFilter::make('type')
                    ->label('Тип отпуска')
                    ->options([
                        1 => 'платена отпуска',
                        2 => 'неплатена отпуска',
                        3 => 'болничен',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Нова отпуска')
                    ->mutateFormDataUsing(function (array $data): array {
                        // Calculate day count automatically
                        if (isset($data['start_date']) && isset($data['end_date'])) {
                            $start = \Carbon\Carbon::parse($data['start_date']);
                            $end = \Carbon\Carbon::parse($data['end_date']);
                            $data['day_count'] = $start->diffInDays($end) + 1;
                        }
                        
                        // Set created_by to current user ID
                        $data['created_by'] = auth()->id();
                        
                        // Handle nullable comment
                        if (isset($data['comment']) && trim($data['comment']) === '') {
                            $data['comment'] = null;
                        }
                        
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Редактиране')
                    ->mutateFormDataUsing(function (array $data): array {
                        // Recalculate day count when editing
                        if (isset($data['start_date']) && isset($data['end_date'])) {
                            $start = \Carbon\Carbon::parse($data['start_date']);
                            $end = \Carbon\Carbon::parse($data['end_date']);
                            $data['day_count'] = $start->diffInDays($end) + 1;
                        }
                        
                        // Handle nullable comment
                        if (isset($data['comment']) && trim($data['comment']) === '') {
                            $data['comment'] = null;
                        }
                        
                        return $data;
                    }),
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
