<?php

namespace App\Filament\Service\Resources\ClientResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\Select;
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
use Viki\Service\Models\Elequent\Region;

class RegionsRelationManager extends RelationManager
{
    protected static string $relationship = 'regions';

    protected static ?string $title = 'Региони';

    protected static ?string $modelLabel = 'Регион';

    protected static ?string $pluralModelLabel = 'Региони';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('region_id')
                    ->label('Избери регион')
                    ->options(Region::where('status', Region::REGION_ACTIVE)->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->helperText('Изберете регион за назначаване към клиента'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Име на региона')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('workplaces_count')
                    ->label('Обекти в региона')
                    ->counts('workplaces')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('workers_count')
                    ->label('Работници в региона')
                    ->getStateUsing(function (Region $record): int {
                        return $record->workplaces()
                            ->withCount('workers')
                            ->get()
                            ->sum('workers_count');
                    })
                    ->badge()
                    ->color('success'),

                BadgeColumn::make('status')
                    ->label('Статус')
                    ->getStateUsing(fn (Region $record): string => 
                        $record->status === Region::REGION_ACTIVE ? 'Активен' : 'Неактивен'
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
                        Region::REGION_ACTIVE => 'Активен',
                        Region::REGION_UNACTIVE => 'Неактивен',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->label('Назначи регион')
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn (Builder $query) => 
                        $query->where('status', Region::REGION_ACTIVE)
                    ),
            ])
            ->actions([
                Tables\Actions\DetachAction::make()
                    ->label('Премахни'),

                Action::make('view_full')
                    ->label('Пълен преглед')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn (Region $record): string => "/service/regions/{$record->id}"),

                Action::make('view_workplaces')
                    ->label('Обекти в региона')
                    ->icon('heroicon-o-building-office')
                    ->color('warning')
                    ->url(fn (Region $record): string => "/service/work-places?tableFilters[region_id][value]={$record->id}")
                    ->visible(fn (Region $record): bool => $record->workplaces_count > 0),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make()
                        ->label('Премахни'),
                ]),
            ])
            ->defaultSort('name', 'asc');
    }
}
