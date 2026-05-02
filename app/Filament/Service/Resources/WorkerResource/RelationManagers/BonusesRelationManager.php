<?php

namespace App\Filament\Service\Resources\WorkerResource\RelationManagers;

use App\Filament\Service\Resources\WorkerResource\Pages\ViewWorker;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use viki\Service\Models\Elequent\WorkerBonus;
use viki\Service\Models\Elequent\Worker;

class BonusesRelationManager extends RelationManager
{
    protected static string $relationship = 'bonuses';

    protected static ?string $title = 'Бонуси и Глоби';

    protected static ?string $modelLabel = 'Бонус/Глоба';

    protected static ?string $pluralModelLabel = 'Бонуси и Глоби';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (auth()->user()?->hasRole('supervisor') && is_a($pageClass, ViewWorker::class, true)) {
            return static::canSupervisorManageWorker($ownerRecord);
        }

        return parent::canViewForRecord($ownerRecord, $pageClass);
    }

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
                    ->label('Сума (€)')
                    ->required()
                    ->numeric()
                    ->step(0.01)
                    ->minValue(0),

                Forms\Components\Select::make('work_place_id')
                    ->label('Обект')
                    ->options(function () {
                        $worker = $this->getOwnerRecord();
                        if ($worker && $worker->workplace) {
                            // Only show the workplace this worker is assigned to
                            return [$worker->work_place_id => $worker->workplace->name];
                        }
                        return [];
                    })
                    ->default(function () {
                        $worker = $this->getOwnerRecord();
                        return $worker ? $worker->work_place_id : null;
                    })
                    ->required()
                    ->disabled(), // Make it read-only since there's only one option

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
                    ->money('EUR', locale: 'bg')
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
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Нов бонус/глоба')
                    ->mutateFormDataUsing(function (array $data): array {
                        $worker = $this->getOwnerRecord();
                        
                        // Set worker_id and work_place_id automatically
                        $data['worker_id'] = $worker->id;
                        $data['work_place_id'] = $worker->work_place_id;
                        
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Редактиране')
                    ->mutateFormDataUsing(function (array $data): array {
                        $worker = $this->getOwnerRecord();
                        
                        // Ensure worker and workplace stay consistent with the owner record
                        $data['worker_id'] = $worker->id;
                        $data['work_place_id'] = $worker->work_place_id;
                        
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

    public function isReadOnly(): bool
    {
        if (is_a($this->getPageClass(), ViewWorker::class, true)) {
            return ! static::canSupervisorManageWorker($this->getOwnerRecord());
        }

        return parent::isReadOnly();
    }

    protected static function canSupervisorManageWorker(?Model $ownerRecord): bool
    {
        $user = auth()->user();

        if (! $user?->hasRole('supervisor')) {
            return false;
        }

        if (! $ownerRecord instanceof Worker || ! $ownerRecord->work_place_id) {
            return false;
        }

        return $user->workPlaces()
            ->where('viki_work_place.id', $ownerRecord->work_place_id)
            ->exists();
    }
}
