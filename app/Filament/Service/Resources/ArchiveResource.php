<?php

namespace App\Filament\Service\Resources;

use App\Filament\Service\Resources\ArchiveResource\Pages;
use viki\Service\Models\Elequent\Archive;
use viki\Service\Models\Elequent\WorkPlace;
use viki\Service\Models\Elequent\Region;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ArchiveResource extends Resource implements HasShieldPermissions
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
                SelectFilter::make('region_id')
                    ->label('Регион')
                    ->options(fn () => static::getRegionFilterOptions())
                    ->searchable()
                    ->placeholder('Всички региони')
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $query, $regionId): Builder => $query->whereHas(
                                'workplace',
                                fn (Builder $workplaceQuery) => $workplaceQuery->where('region_id', $regionId)
                            )
                        );
                    }),

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
                $user = auth()->user();
                
                if (!$user) {
                    return $query->whereRaw('1 = 0');
                }
                
                $userRoles = $user->roles->pluck('name')->toArray();
                
                // Admin and Super Admin see all archives
                if (in_array('admin', $userRoles) || in_array('super_admin', $userRoles)) {
                    return $query;
                }
                
                // Manager sees archives only in their region
                if (in_array('manager', $userRoles)) {
                    $managerRegions = $user->regions->pluck('id')->toArray();
                    if (!empty($managerRegions)) {
                        $workplaceIds = WorkPlace::where('status', 0) // WORK_PLACE_ACTIVE = 0
                            ->whereIn('region_id', $managerRegions)
                            ->pluck('id');
                        
                        return $query->whereIn('work_place_id', $workplaceIds);
                    }
                }
                
                // Supervisor sees archives only for their workplaces
                if (in_array('supervisor', $userRoles)) {
                    $supervisorWorkplaces = $user->workPlaces->pluck('id')->toArray();
                    if (!empty($supervisorWorkplaces)) {
                        return $query->whereIn('work_place_id', $supervisorWorkplaces);
                    }
                }
                
                // Default: no access for other roles
                return $query->whereRaw('1 = 0');
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
                            ->viewData(fn (Archive $record) => [
                                'record' => $record,
                                'archiveMonthOptions' => static::getArchiveMonthOptions($record),
                                'selectedMonthLabel' => Carbon::parse($record->date)->format('m.Y'),
                                'exportUrl' => route('service.archives.export', ['archive' => $record->getKey()])
                            ])
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(false),
            ]);
    }

    protected static function getRegionFilterOptions(): array
    {
        $user = Auth::user();

        if (!$user) {
            return [];
        }

        $roles = $user->roles->pluck('name')->toArray();

        if (in_array('admin', $roles) || in_array('super_admin', $roles)) {
            return Region::query()
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray();
        }

        if (in_array('manager', $roles)) {
            $regionIds = $user->regions()
                ->pluck('viki_regions.id')
                ->unique()
                ->values()
                ->all();

            if (empty($regionIds)) {
                return [];
            }

            return Region::query()
                ->whereIn('id', $regionIds)
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray();
        }

        if (in_array('supervisor', $roles)) {
            $regionIds = $user->workPlaces()
                ->pluck('region_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (empty($regionIds)) {
                return [];
            }

            return Region::query()
                ->whereIn('id', $regionIds)
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray();
        }

        return [];
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

    public static function getPermissionPrefixes(): array
    {
        return [
            "view",
            "view_any",
            "create",
            "update",
            "delete",
            "delete_any",
        ];
    }

    protected static function getArchiveMonthOptions(Archive $record): array
    {
        $oneYearAgo = Carbon::now()->subYear()->startOfDay();
        $now = Carbon::now();

        return Archive::query()
            ->where('work_place_id', $record->work_place_id)
            ->whereBetween('date', [$oneYearAgo, $now])
            ->orderBy('date', 'desc')
            ->get()
            ->map(function (Archive $archive) {
                $label = Carbon::parse($archive->date)->format('m.Y');

                return [
                    'label' => $label,
                    'url' => self::getUrl('view', ['record' => $archive]),
                ];
            })
            ->unique('label')
            ->values()
            ->toArray();
    }
}
