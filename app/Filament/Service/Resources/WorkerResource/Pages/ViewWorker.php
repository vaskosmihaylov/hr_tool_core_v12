<?php

namespace App\Filament\Service\Resources\WorkerResource\Pages;

use App\Filament\Service\Resources\WorkerResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;


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
                        TextEntry::make('note')
                            ->label('Бележки')
                            ->placeholder('Няма въведени бележки'),
                    ])
                    ->columns(2),

                Section::make('💼 Служебни данни')
                    ->schema([
                        TextEntry::make('start_date')
                            ->label('Дата на започване')
                            ->date('d.m.Y'),
                        TextEntry::make('unactive_from_date')
                            ->label('Дата на приключване')
                            ->date('d.m.Y')
                            ->placeholder('Безсрочен договор'),
                        TextEntry::make('neto_salary')
                            ->label('Основна заплата')
                            ->money('BGN'),
                        TextEntry::make('hours_per_day')
                            ->label('Работно време')
                            ->suffix(' часа'),
                        TextEntry::make('type_working')
                            ->label('Тип работа')
                            ->getStateUsing(function ($record) {
                                return $record->type_working == \viki\Service\Models\Elequent\Worker::WORKING_STANDART ? 'стандартно' : 'сумарно';
                            })
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'стандартно' => 'success',
                                'сумарно' => 'info',
                                default => 'gray',
                            }),
                        TextEntry::make('status')
                            ->label('Статус')
                            ->getStateUsing(function ($record) {
                                // Map status IDs directly to properly capitalized Bulgarian text
                                return match($record->status) {
                                    0 => 'Активен',   // USER_ACTIVE
                                    1 => 'Неактивен', // USER_UNACTIVE  
                                    default => 'Неизвестен'
                                };
                            })
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'Активен' => 'success',
                                'Неактивен' => 'danger',
                                default => 'gray',
                            }),
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

                Section::make('🏖️ Отпуски и почивки')
                    ->schema([
                        TextEntry::make('vacations_display')
                            ->label('Налични отпуски')
                            ->getStateUsing(function ($record) {
                                if ($record->vacations()->count() === 0) {
                                    return 'Няма въведени отпуски';
                                }
                                
                                $vacations = $record->vacations()->latest()->take(5)->get();
                                $display = [];
                                
                                foreach ($vacations as $vacation) {
                                    $typeText = match($vacation->type) {
                                        1 => 'Платен отпуск',
                                        2 => 'Неплатен отпуск', 
                                        3 => 'Болничен',
                                        default => 'Неизвестен тип'
                                    };
                                    
                                    $display[] = $typeText . ': ' . 
                                               $vacation->start_date . ' - ' . $vacation->end_date .
                                               ($vacation->comment ? ' (' . $vacation->comment . ')' : '');
                                }
                                
                                if ($record->vacations()->count() > 5) {
                                    $display[] = '... и още ' . ($record->vacations()->count() - 5) . ' отпуски';
                                }
                                
                                return implode("
", $display);
                            })
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Section::make('💰 Бонуси и глоби')
                    ->schema([
                        TextEntry::make('bonuses_display')
                            ->label('Налични бонуси/глоби')
                            ->getStateUsing(function ($record) {
                                if ($record->bonus()->count() === 0) {
                                    return 'Няма въведени бонуси или глоби';
                                }
                                
                                $bonuses = $record->bonus()->latest()->take(5)->get();
                                $display = [];
                                
                                foreach ($bonuses as $bonus) {
                                    $typeText = $bonus->type == 0 ? 'Бонус' : 'Глоба';
                                    $dates = explode('-', $bonus->for_month);
                                    $month = $dates[0] . "-" . $dates[1];
                                    
                                    $display[] = $typeText . ': ' . number_format($bonus->sum, 2) . ' лв. (' . 
                                               $month . ') - ' . $bonus->workplace->name;
                                }
                                
                                if ($record->bonus()->count() > 5) {
                                    $display[] = '... и още ' . ($record->bonus()->count() - 5) . ' записа';
                                }
                                
                                return implode("
", $display);
                            })
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }
}
