<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use App\Filament\Resources\ActivityLogResource\RelationManagers;
use Spatie\Activitylog\Models\Activity;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    
    protected static ?string $navigationGroup = 'System';
    
    protected static ?int $navigationSort = 10;
    
    protected static ?string $navigationLabel = 'Activity Logs';

    public static function canCreate(): bool
    {
        return false; // Activity logs are created automatically
    }

    public static function canEdit($record): bool
    {
        return false; // Activity logs should not be edited
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('log_name')
                    ->disabled(),
                Forms\Components\TextInput::make('description')
                    ->disabled(),
                Forms\Components\TextInput::make('subject_type')
                    ->disabled(),
                Forms\Components\TextInput::make('subject_id')
                    ->disabled(),
                Forms\Components\TextInput::make('causer_type')
                    ->disabled(),
                Forms\Components\TextInput::make('causer_id')
                    ->disabled(),
                Forms\Components\Textarea::make('properties')
                    ->disabled()
                    ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('log_name')
                    ->label('Log Name')
                    ->badge()
                    ->color('primary')
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Subject')
                    ->formatStateUsing(fn ($state) => class_basename($state))
                    ->badge()
                    ->color('secondary'),
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('User')
                    ->searchable()
                    ->default('System'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable()
                    ->default(''),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('log_name')
                    ->label('Log Type')
                    ->options([
                        'default' => 'Default',
                        'user' => 'User',
                        'role' => 'Role',
                        'permission' => 'Permission',
                        'setting' => 'Setting',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                // View action removed - logs are read-only
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
        ];
    }
}
