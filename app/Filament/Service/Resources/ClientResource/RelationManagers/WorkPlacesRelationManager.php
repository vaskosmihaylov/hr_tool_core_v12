<?php

namespace App\Filament\Service\Resources\ClientResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Viki\Service\Models\Elequent\WorkPlace;
use Viki\Service\Models\Elequent\Region;

class WorkPlacesRelationManager extends RelationManager
{
    protected static string $relationship = 'workplaces';

    protected static ?string $title = 'Обекти';

    protected static ?string $modelLabel = 'Обект';

    protected static ?string $pluralModelLabel = 'Обекти';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('🏢 Основни данни')
                    ->schema([
                        TextInput::make('name')
                            ->label('Име на обекта')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        Textarea::make('address')
                            ->label('Адрес')
                            ->maxLength(500)
                            ->rows(3)
                            ->columnSpan(2),
                    ])
                    ->columns(2),

                Section::make('🗺️ Организация')
                    ->schema([
                        Select::make('region_id')
                            ->label('Регион')
                            ->options(Region::where('status', Region::REGION_ACTIVE)->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpan(1),

                        TextInput::make('budget')
                            ->label('Бюджет (лв.)')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix('лв.')
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Section::make('⚙️ Настройки')
                    ->schema([
                        Select::make('status')
                            ->label('Статус')
                            ->options([
                                WorkPlace::WORK_PLACE_ACTIVE => 'Активен',
                                WorkPlace::WORK_PLACE_UNACTIVE => 'Неактивен',
                            ])
                            ->required()
                            ->default(WorkPlace::WORK_PLACE_ACTIVE)
                            ->columnSpan(1),
                    ])
                    ->columns(1),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Име на обекта')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('address')
                    ->label('Адрес')
                    ->searchable()
                    ->limit(30)
                    ->wrap(),

                TextColumn::make('region.name')
                    ->label('Регион')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('budget')
                    ->label('Бюджет')
                    ->money('BGN')
                    ->sortable(),

                TextColumn::make('workers_count')
                    ->label('Работници')
                    ->counts('workers')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                BadgeColumn::make('status')
                    ->label('Статус')
                    ->getStateUsing(fn (WorkPlace $record): string => 
                        $record->status === WorkPlace::WORK_PLACE_ACTIVE ? 'Активен' : 'Неактивен'
                    )
                    ->colors([
                        'success' => static fn ($state): bool => $state === 'Активен',
                        'danger' => static fn ($state): bool => $state === 'Неактивен',
                    ]),

                TextColumn::make('created_at')
                    ->label('Създаден на')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        WorkPlace::WORK_PLACE_ACTIVE => 'Активен',
                        WorkPlace::WORK_PLACE_UNACTIVE => 'Неактивен',
                    ]),

                SelectFilter::make('region_id')
                    ->label('Регион')
                    ->relationship('region', 'name'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Създай обект')
                    ->mutateFormDataUsing(function (array $data): array {
                        // Client ID will be automatically set by the relation manager
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Редактиране'),

                Tables\Actions\DeleteAction::make()
                    ->label('Изтриване'),

                Action::make('view_full')
                    ->label('Пълен преглед')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn (WorkPlace $record): string => "/service/work-places/{$record->id}"),

                Action::make('view_workers')
                    ->label('Работници')
                    ->icon('heroicon-o-users')
                    ->color('success')
                    ->url(fn (WorkPlace $record): string => "/service/workers?tableFilters[work_place_id][value]={$record->id}")
                    ->visible(fn (WorkPlace $record): bool => $record->workers_count > 0),
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
