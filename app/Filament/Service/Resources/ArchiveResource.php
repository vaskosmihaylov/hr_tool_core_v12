<?php

namespace App\Filament\Service\Resources;

use App\Filament\Service\Resources\ArchiveResource\Pages;
use viki\Service\Models\Elequent\Archive;
use viki\Service\Models\Elequent\WorkPlace;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ArchiveResource extends Resource
{
    protected static ?string $model = Archive::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationLabel = 'Архив';

    protected static ?string $modelLabel = 'Архив';

    protected static ?string $pluralModelLabel = 'Архив';

    protected static ?string $navigationGroup = '👥 Човешки ресурси';

    protected static ?int $navigationSort = 8;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('work_place_id')
                    ->label('Обект')
                    ->relationship('workplace', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),

                Forms\Components\DatePicker::make('date')
                    ->label('Дата на архива')
                    ->required()
                    ->displayFormat('d.m.Y')
                    ->native(false),

                Forms\Components\Textarea::make('json_data')
                    ->label('JSON данни')
                    ->rows(10)
                    ->columnSpanFull()
                    ->helperText('Архивирани данни от присъствената форма в JSON формат'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('workplace.name')
                    ->label('Обект')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('workplace.region.name')
                    ->label('Регион')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('date')
                    ->label('Дата на архива')
                    ->date('d.m.Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('data_size')
                    ->label('Размер на данните')
                    ->state(function (Archive $record): string {
                        $size = strlen($record->json_data);
                        if ($size > 1024 * 1024) {
                            return number_format($size / (1024 * 1024), 2) . ' MB';
                        } elseif ($size > 1024) {
                            return number_format($size / 1024, 2) . ' KB';
                        }
                        return $size . ' B';
                    })
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Създаден на')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('work_place_id')
                    ->label('Обект')
                    ->relationship('workplace', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('От дата')
                            ->native(false),
                        Forms\Components\DatePicker::make('until')
                            ->label('До дата')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Преглед'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('date', 'desc')
            ->modifyQueryUsing(function (Builder $query) {
                $user = Auth::user();
                
                // Apply role-based filtering
                if ($user->hasRole('manager')) {
                    $regionIds = \viki\Service\Models\Elequent\VikiUser::getCurrentUserRegionId($user->id);
                    $workplaceIds = WorkPlace::where('status', WorkPlace::WORK_PLACE_ACTIVE)
                        ->whereIn('region_id', $regionIds)
                        ->pluck('id');
                    
                    return $query->whereIn('work_place_id', $workplaceIds);
                } elseif ($user->hasRole('supervisor')) {
                    $vikiUser = \viki\Service\Models\Elequent\VikiUser::find($user->id);
                    $workplaceIds = $vikiUser->workPlaces()->pluck('viki_work_place.id');
                    
                    return $query->whereIn('work_place_id', $workplaceIds);
                }
                
                // Admin sees all archives
                return $query;
            });
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Основна информация')
                    ->schema([
                        Infolists\Components\TextEntry::make('workplace.name')
                            ->label('Обект'),

                        Infolists\Components\TextEntry::make('workplace.region.name')
                            ->label('Регион'),

                        Infolists\Components\TextEntry::make('date')
                            ->label('Дата на архива')
                            ->date('d.m.Y'),

                        Infolists\Components\TextEntry::make('data_preview')
                            ->label('Преглед на данните')
                            ->state(function (Archive $record): string {
                                $data = json_decode($record->json_data, true);
                                if (!$data) {
                                    return 'Невалидни JSON данни';
                                }
                                
                                $preview = "Архивирани присъствени данни:\n";
                                $preview .= "• Общо активности: " . count($data) . "\n";
                                
                                foreach ($data as $key => $activity) {
                                    if (isset($activity['workPlaceActivityWorkers'])) {
                                        $workerCount = count($activity['workPlaceActivityWorkers']);
                                        $preview .= "• Активност {$key}: {$workerCount} работници\n";
                                    }
                                }
                                
                                return $preview;
                            })
                            ->markdown(),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Присъствена форма')
                    ->schema([
                        Infolists\Components\ViewEntry::make('presence_table')
                            ->label('')
                            ->view('filament.service.archive.presence-table')
                            ->viewData(fn (Archive $record) => ['record' => $record])
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(false),

                Infolists\Components\Section::make('JSON данни')
                    ->schema([
                        Infolists\Components\TextEntry::make('json_data')
                            ->label('Пълни данни')
                            ->state(function (Archive $record): string {
                                $data = json_decode($record->json_data, true);
                                return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                            })
                            ->copyable()
                            ->extraAttributes(['class' => 'font-mono text-xs'])
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(true),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArchives::route('/'),
            'view' => Pages\ViewArchive::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        // Archives are created automatically by the system
        return false;
    }

    public static function canEdit($record = null): bool
    {
        // Archives are read-only
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        // Show archives from last year only, matching the controller logic
        $oneYearAgo = Carbon::now()->subYear()->startOfDay();
        $count = static::getModel()::whereBetween('date', [$oneYearAgo, Carbon::now()])->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'gray';
    }
}
