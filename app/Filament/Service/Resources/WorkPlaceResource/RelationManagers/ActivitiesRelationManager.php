<?php

namespace App\Filament\Service\Resources\WorkPlaceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use viki\Service\Models\Elequent\WorkPlaceActivity;

class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $title = 'Дейности';

    protected static ?string $modelLabel = 'Дейност';

    protected static ?string $pluralModelLabel = 'Дейности';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('📋 Информация за дейността')
                    ->schema([
                        TextInput::make('activity')
                            ->label('Име на дейността')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        TextInput::make('worker_count')
                            ->label('Брой работници')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->columnSpan(1),

                        Select::make('type_working')
                            ->label('Тип работа')
                            ->options([
                                WorkPlaceActivity::WORKING_STANDART => 'Стандартно',
                                WorkPlaceActivity::WORKING_BY_HOURS => 'Сумарно',
                            ])
                            ->required()
                            ->default(WorkPlaceActivity::WORKING_STANDART)
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Section::make('💰 Финансова информация')
                    ->schema([
                        TextInput::make('neto_salary')
                            ->label('Нето заплата (€)')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix('€')
                            ->columnSpan(1),

                    ])
                    ->columns(1),

            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('activity')
            ->columns([
                TextColumn::make('activity')
                    ->label('Дейност')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('worker_count')
                    ->label('Работници')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                BadgeColumn::make('type_working')
                    ->label('Тип работа')
                    ->getStateUsing(fn (WorkPlaceActivity $record): string => 
                        $record->type_working === WorkPlaceActivity::WORKING_STANDART ? 'Стандартно' : 'Сумарно'
                    )
                    ->colors([
                        'primary' => static fn ($state): bool => $state === 'Стандартно',
                        'warning' => static fn ($state): bool => $state === 'Сумарно',
                    ]),

                TextColumn::make('neto_salary')
                    ->label('Нето заплата')
                    ->money('EUR')
                    ->sortable(),

                TextColumn::make('total_cost')
                    ->label('Общо разходи')
                    ->getStateUsing(fn (WorkPlaceActivity $record): float =>
                        $record->neto_salary
                    )
                    ->money('EUR')
                    ->sortable(),

                TextColumn::make('date')
                    ->label('Планирана дата')
                    ->date('d.m.Y')
                    ->sortable()
                    ->placeholder('Постоянна'),

                TextColumn::make('createdBy.name')
                    ->label('Създадена от')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Създадена на')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type_working')
                    ->label('Тип работа')
                    ->options([
                        WorkPlaceActivity::WORKING_STANDART => 'Стандартно',
                        WorkPlaceActivity::WORKING_BY_HOURS => 'Сумарно',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Създай дейност')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();
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
