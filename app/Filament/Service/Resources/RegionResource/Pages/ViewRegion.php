<?php

namespace App\Filament\Service\Resources\RegionResource\Pages;

use App\Filament\Service\Resources\RegionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;


class ViewRegion extends ViewRecord
{
    protected static string $resource = RegionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Редактиране'),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('🗺️ Информация за регион')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Име на регион')
                            ->size('lg')
                            ->weight('bold'),

                        TextEntry::make('status')
                            ->label('Статус')
                            ->getStateUsing(fn ($record) => \viki\Service\Models\Elequent\Region::regionStatuses()[$record->status])
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'Активен' => 'success',
                                'Неактивен' => 'danger',
                                default => 'gray',
                            }),
                    ])
                    ->columns(2),

                Section::make('📊 Статистики')
                    ->schema([
                        TextEntry::make('workers_count')
                            ->label('Общо работници')
                            ->getStateUsing(fn ($record) => $record->workers()->count())
                            ->badge()
                            ->color('info'),

                        TextEntry::make('active_workers_count')
                            ->label('Активни работници')
                            ->getStateUsing(fn ($record) => $record->workers()->where('status', \viki\Service\Models\Elequent\Worker::WORKER_ACTIVE)->count())
                            ->badge()
                            ->color('success'),

                        TextEntry::make('workplaces_count')
                            ->label('Общо обекти')
                            ->getStateUsing(fn ($record) => $record->workplaces()->count())
                            ->badge()
                            ->color('warning'),

                        TextEntry::make('active_workplaces_count')
                            ->label('Активни обекти')
                            ->getStateUsing(fn ($record) => $record->workplaces()->where('status', \viki\Service\Models\Elequent\WorkPlace::WORK_PLACE_ACTIVE)->count())
                            ->badge()
                            ->color('success'),
                    ])
                    ->columns(2),

                Section::make('📅 Системна информация')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Създаден на')
                            ->dateTime('d.m.Y H:i:s'),

                        TextEntry::make('updated_at')
                            ->label('Обновен на')
                            ->dateTime('d.m.Y H:i:s'),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }
}
