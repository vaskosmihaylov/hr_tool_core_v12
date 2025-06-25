<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SettingResource\Pages;
use App\Filament\Resources\SettingResource\RelationManagers;
use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Section;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    
    protected static ?string $navigationGroup = 'System';
    
    protected static ?int $navigationSort = 11;
    
    protected static ?string $navigationLabel = 'Settings';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Setting Information')
                    ->schema([
                        Forms\Components\TextInput::make('key')
                            ->required()
                            ->unique(Setting::class, 'key', ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('Unique setting key (e.g., app.timezone, mail.from_address)'),
                        Forms\Components\TextInput::make('label')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Human-readable label for this setting'),
                        Forms\Components\Select::make('type')
                            ->required()
                            ->options([
                                'text' => 'Text',
                                'textarea' => 'Textarea',
                                'number' => 'Number',
                                'boolean' => 'Boolean',
                                'select' => 'Select',
                                'email' => 'Email',
                                'url' => 'URL',
                            ])
                            ->default('text')
                            ->live()
                            ->helperText('The input type for this setting'),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->helperText('Whether this setting is active'),
                    ])->columns(2),
                    
                Section::make('Setting Value')
                    ->schema([
                        Forms\Components\Textarea::make('value')
                            ->rows(3)
                            ->helperText('The current value of this setting'),
                        Forms\Components\Textarea::make('description')
                            ->rows(2)
                            ->helperText('Description of what this setting controls'),
                        Forms\Components\Textarea::make('options')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'select')
                            ->helperText('JSON array of options for select type: ["option1", "option2"]')
                            ->placeholder('["Option 1", "Option 2", "Option 3"]'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('label')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('value')
                    ->limit(50)
                    ->placeholder('No value set'),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->colors([
                        'primary' => 'text',
                        'secondary' => 'textarea',
                        'success' => 'boolean',
                        'warning' => 'number',
                        'danger' => 'select',
                    ]),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'text' => 'Text',
                        'textarea' => 'Textarea',
                        'number' => 'Number',
                        'boolean' => 'Boolean',
                        'select' => 'Select',
                        'email' => 'Email',
                        'url' => 'URL',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only')
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListSettings::route('/'),
            'create' => Pages\CreateSetting::route('/create'),
            'edit' => Pages\EditSetting::route('/{record}/edit'),
        ];
    }
}
