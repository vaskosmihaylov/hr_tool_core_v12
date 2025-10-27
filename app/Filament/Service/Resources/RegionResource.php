<?php

namespace App\Filament\Service\Resources;

use App\Filament\Service\Resources\RegionResource\Pages;
use viki\Service\Models\Elequent\Region;
use viki\Service\Models\Elequent\Worker;
use viki\Service\Models\Elequent\WorkPlace;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\Action;

class RegionResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Region::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = 'Региони';

    protected static ?string $modelLabel = 'Регион';

    protected static ?string $pluralModelLabel = 'Региони';

    protected static ?string $navigationGroup = '🏢 Организация';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('🗺️ Информация за регион')
                    ->description('Основни данни за региона')
                    ->schema([
                        TextInput::make('name')
                            ->label('Име на регион')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->columnSpan(2),

                        Select::make('status')
                            ->label('Статус')
                            ->options(Region::regionStatuses())
                            ->required()
                            ->default(Region::REGION_ACTIVE)
                            ->columnSpan(1),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Регион')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->size('lg'),



                TextColumn::make('workplaces_count')
                    ->label('Обекти')
                    ->counts('workplaces')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('workers_count')
                    ->label('Работници')
                    ->counts('workers')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('active_workers_count')
                    ->label('Активни работници')
                    ->getStateUsing(fn (Region $record): int => 
                        $record->workers()->where('status', 0)->count()
                    )
                    ->badge()
                    ->color('success')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Създаден')
                    ->date('d.m.Y')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Обновен')
                    ->date('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name', 'asc')
            ->persistSortInSession()
            ->persistSearchInSession()
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(Region::regionStatuses()),
            ])

            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Изтриване')
                        ->requiresConfirmation()
                        ->modalHeading('Изтриване на региони')
                        ->modalDescription('Сигурни ли сте, че искате да изтриете избраните региони? Това действие не може да бъде отменено.')
                        ->modalSubmitActionLabel('Да, изтрий'),
                ]),
            ])
            ->defaultSort('name', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegions::route('/'),
            'create' => Pages\CreateRegion::route('/create'),
            'view' => Pages\ViewRegion::route('/{record}'),
            'edit' => Pages\EditRegion::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', Region::REGION_ACTIVE)->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'success';
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Hide from Manager role - only visible to Admin and Super Admin
        return auth()->user()->hasRole(['admin', 'super_admin']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }

    public static function getGlobalSearchResultTitle($record): string
    {
        return $record->name;
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'Статус' => Region::regionStatuses()[$record->status],
            'Работници' => $record->workers()->count(),
            'Обекти' => $record->workplaces()->count(),
        ];
    }

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
        ];
    }
}
