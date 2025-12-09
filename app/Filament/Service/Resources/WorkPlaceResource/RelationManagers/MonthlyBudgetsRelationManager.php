<?php

namespace App\Filament\Service\Resources\WorkPlaceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use viki\Service\Models\Elequent\WorkPlaceMonthlyBudget;

class MonthlyBudgetsRelationManager extends RelationManager
{
    protected static string $relationship = 'monthlyBudget';

    protected static ?string $title = 'Месечни бюджети';

    protected static ?string $modelLabel = 'Месечен бюджет';

    protected static ?string $pluralModelLabel = 'Месечни бюджети';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('💰 Бюджетна информация')
                    ->description('Определяне на специфичен бюджет за даден месец')
                    ->schema([
                        TextInput::make('budget')
                            ->label('Бюджет (лв.)')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix('лв.')
                            ->helperText('Специфичен бюджет за избрания месец')
                            ->columnSpan(1),

                        DatePicker::make('valid_from')
                            ->label('Валиден от дата')
                            ->required()
                            ->default(now()->format('Y-m-01'))
                            ->helperText('Първия ден от месеца, за който важи бюджета')
                            ->displayFormat('d.m.Y')
                            ->columnSpan(1),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('budget')
            ->columns([
                TextColumn::make('valid_from')
                    ->label('Валиден от')
                    ->date('d.m.Y')
                    ->sortable()
                    ->description(fn (WorkPlaceMonthlyBudget $record): string => 
                        'Месец: ' . date('m/Y', strtotime($record->valid_from))
                    ),

                TextColumn::make('budget')
                    ->label('Бюджет')
                    ->money('BGN')
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('budget_difference')
                    ->label('Разлика от основния')
                    ->getStateUsing(function (WorkPlaceMonthlyBudget $record): float {
                        $mainBudget = $record->workplace->budget ?? 0;
                        return $record->budget - $mainBudget;
                    })
                    ->money('BGN')
                    ->color(fn ($state): string => $state >= 0 ? 'success' : 'danger')
                    ->icon(fn ($state): string => $state >= 0 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down'),

                TextColumn::make('percentage_change')
                    ->label('% промяна')
                    ->getStateUsing(function (WorkPlaceMonthlyBudget $record): string {
                        $mainBudget = $record->workplace->budget ?? 1;
                        $percentage = (($record->budget - $mainBudget) / $mainBudget) * 100;
                        return number_format($percentage, 1) . '%';
                    })
                    ->badge()
                    ->color(function (WorkPlaceMonthlyBudget $record): string {
                        $mainBudget = $record->workplace->budget ?? 1;
                        $percentage = (($record->budget - $mainBudget) / $mainBudget) * 100;
                        
                        if ($percentage > 0) return 'success';
                        if ($percentage < 0) return 'danger';
                        return 'gray';
                    }),

                TextColumn::make('created_at')
                    ->label('Създаден на')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('current_year')
                    ->label('Текуща година')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereYear('valid_from', now()->year)
                    ),

                Tables\Filters\Filter::make('increased_budget')
                    ->label('Увеличен бюджет')
                    ->query(function (Builder $query): Builder {
                        return $query->whereHas('workplace', function ($subQuery) {
                            $subQuery->whereRaw('viki_workplace_monthly_budget.budget > viki_work_place.budget');
                        });
                    }),

                Tables\Filters\Filter::make('decreased_budget')
                    ->label('Намален бюджет')
                    ->query(function (Builder $query): Builder {
                        return $query->whereHas('workplace', function ($subQuery) {
                            $subQuery->whereRaw('viki_workplace_monthly_budget.budget < viki_work_place.budget');
                        });
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Добави месечен бюджет')
                    ->mutateFormDataUsing(function (array $data): array {
                        // Ensure valid_from is set to first day of month
                        if (isset($data['valid_from'])) {
                            $data['valid_from'] = date('Y-m-01', strtotime($data['valid_from']));
                        }
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
            ->defaultSort('valid_from', 'desc');
    }
}
