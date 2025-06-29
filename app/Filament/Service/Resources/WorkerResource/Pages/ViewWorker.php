<?php

namespace App\Filament\Service\Resources\WorkerResource\Pages;

use App\Filament\Service\Resources\WorkerResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\BadgeEntry;

class ViewWorker extends ViewRecord
{
    protected static string $resource = WorkerResource::class;

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
                Section::make('📋 Лични данни')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Име'),
                        TextEntry::make('middle_name')
                            ->label('Презиме'),
                        TextEntry::make('family_name')
                            ->label('Фамилия'),
                        TextEntry::make('egn')
                            ->label('ЕГН'),
                        TextEntry::make('position')
                            ->label('Длъжност'),
                    ])
                    ->columns(2),

                Section::make('💼 Служебни данни')
                    ->schema([
                        TextEntry::make('date_start_job')
                            ->label('Дата на започване')
                            ->date('d.m.Y'),
                        TextEntry::make('date_end_job')
                            ->label('Дата на приключване')
                            ->date('d.m.Y')
                            ->placeholder('Безсрочен договор'),
                        TextEntry::make('basic_salary')
                            ->label('Основна заплата')
                            ->money('BGN'),
                        TextEntry::make('additional_salary')
                            ->label('Допълнителна заплата')
                            ->money('BGN'),
                        TextEntry::make('working_time')
                            ->label('Работно време')
                            ->suffix(' часа'),
                        BadgeEntry::make('status')
                            ->label('Статус')
                            ->getStateUsing(fn ($record) => \viki\Service\Models\Elequent\Worker::workerStatuses()[$record->status])
                            ->colors([
                                'success' => 'Активен',
                                'danger' => 'Неактивен',
                            ]),
                    ])
                    ->columns(2),

                Section::make('🏢 Месторабота')
                    ->schema([
                        TextEntry::make('region.name')
                            ->label('Регион'),
                        TextEntry::make('workplace.name')
                            ->label('Обект'),
                        TextEntry::make('workplaceActivity.activity')
                            ->label('Дейност'),
                        TextEntry::make('workplaceActivity.hour_rate')
                            ->label('Часова ставка')
                            ->money('BGN'),
                    ])
                    ->columns(2),

                Section::make('📊 Системна информация')
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
