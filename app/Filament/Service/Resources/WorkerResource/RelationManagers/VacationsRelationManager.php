<?php

namespace App\Filament\Service\Resources\WorkerResource\RelationManagers;

use App\Filament\Service\Resources\WorkerResource\Pages\ViewWorker;
use App\Services\VacationOverlapService;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use viki\Service\Models\Elequent\Vacation;
use viki\Service\Models\Elequent\Worker;

class VacationsRelationManager extends RelationManager
{
    protected static string $relationship = 'vacations';

    protected static ?string $title = 'Отпуски';

    protected static ?string $modelLabel = 'Отпуска';

    protected static ?string $pluralModelLabel = 'Отпуски';

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
                Forms\Components\DatePicker::make('start_date')
                    ->label('Начална дата')
                    ->required(),

                Forms\Components\DatePicker::make('end_date')
                    ->label('Крайна дата')
                    ->required()
                    ->afterOrEqual('start_date'),

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

                Forms\Components\Hidden::make('status')
                    ->default(1),
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
                        return match ($record->type) {
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
                        return match ($record->status) {
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
                        $worker = $this->getOwnerRecord();

                        // Calculate day count automatically
                        if (isset($data['start_date']) && isset($data['end_date'])) {
                            $start = \Carbon\Carbon::parse($data['start_date']);
                            $end = \Carbon\Carbon::parse($data['end_date']);
                            $data['day_count'] = $start->diffInDays($end) + 1;
                        }

                        if ($worker) {
                            $data['worker_id'] = $worker->id;
                        }

                        // Set created_by to current user ID
                        $data['created_by'] = auth()->id();

                        // Vacations created by HR are auto-approved
                        $data['status'] = 1;

                        // Handle nullable comment
                        if (isset($data['comment']) && trim($data['comment']) === '') {
                            $data['comment'] = null;
                        }

                        return $data;
                    })
                    ->before(function (Tables\Actions\CreateAction $action, array $data): void {
                        $this->haltIfVacationOverlaps($action, $data);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Редактиране')
                    ->mutateFormDataUsing(function (array $data): array {
                        $worker = $this->getOwnerRecord();

                        // Recalculate day count when editing
                        if (isset($data['start_date']) && isset($data['end_date'])) {
                            $start = \Carbon\Carbon::parse($data['start_date']);
                            $end = \Carbon\Carbon::parse($data['end_date']);
                            $data['day_count'] = $start->diffInDays($end) + 1;
                        }

                        if ($worker) {
                            $data['worker_id'] = $worker->id;
                        }

                        // Handle nullable comment
                        if (isset($data['comment']) && trim($data['comment']) === '') {
                            $data['comment'] = null;
                        }

                        // Ensure status stays approved when edited through HR interface
                        $data['status'] = 1;

                        return $data;
                    })
                    ->before(function (Tables\Actions\EditAction $action, array $data, Vacation $record): void {
                        $this->haltIfVacationOverlaps($action, $data, $record);
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

    private function haltIfVacationOverlaps(
        Tables\Actions\Action $action,
        array $data,
        ?Vacation $record = null
    ): void {
        $worker = $this->getOwnerRecord();

        if (! $worker || ! isset($data['start_date'], $data['end_date'])) {
            return;
        }

        $overlap = app(VacationOverlapService::class)->findOverlap(
            $worker->id,
            $data['start_date'],
            $data['end_date'],
            $record?->getKey()
        );

        if (! $overlap) {
            return;
        }

        $this->sendVacationOverlapNotification($overlap);

        $action->halt();
    }

    private function sendVacationOverlapNotification(Vacation $vacation): void
    {
        $startDate = Carbon::parse($vacation->start_date)->format('d.m.Y');
        $endDate = Carbon::parse($vacation->end_date)->format('d.m.Y');

        Notification::make()
            ->title('Дублирана отпуска')
            ->body("Избраният период се припокрива със съществуваща отпуска от {$startDate} до {$endDate}. Моля, изберете период без дублирани дни.")
            ->danger()
            ->persistent()
            ->send();
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
