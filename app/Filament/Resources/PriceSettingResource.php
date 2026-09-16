<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PriceSettingResource\Pages;
use App\Models\PriceSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PriceSettingResource extends Resource
{
    protected static ?string $model = PriceSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Narx sozlamalari';

    protected static ?string $modelLabel = 'narx sozlamasi';

    protected static ?string $pluralModelLabel = 'narx sozlamalari';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('price_per_sqm')
                    ->label('1 m² narxi')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->suffix('so\'m'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('price_per_sqm')
                    ->label('1 m² narxi')
                    ->money('uzs', divideBy: 1),
                Tables\Columns\TextColumn::make('updatedBy.name')
                    ->label('Kim yangiladi'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Qachon yangilandi')
                    ->dateTime('d.m.Y H:i'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPriceSettings::route('/'),
            'edit' => Pages\EditPriceSetting::route('/{record}/edit'),
        ];
    }
}
