<?php

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Buyurtmalar';

    protected static ?string $modelLabel = 'buyurtma';

    protected static ?string $pluralModelLabel = 'buyurtmalar';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('worker_id')
                    ->label('Ishchi')
                    ->relationship('worker', 'ism')
                    ->disabled(),
                Forms\Components\TextInput::make('customer_name')
                    ->label('Mijoz ismi')
                    ->disabled(),
                Forms\Components\TextInput::make('customer_phone')
                    ->label('Mijoz telefoni')
                    ->disabled(),
                Forms\Components\TextInput::make('square_meters')
                    ->label('Kvadrat metr')
                    ->disabled(),
                Forms\Components\TextInput::make('price_per_sqm_snapshot')
                    ->label('1 m² narxi (buyurtma vaqtida)')
                    ->disabled(),
                Forms\Components\TextInput::make('total_price')
                    ->label('Umumiy narx')
                    ->disabled(),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options(collect(OrderStatus::cases())->mapWithKeys(fn (OrderStatus $status) => [$status->value => $status->label()]))
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('worker.ism')
                    ->label('Ishchi')
                    ->searchable(),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Mijoz ismi')
                    ->searchable(),
                Tables\Columns\TextColumn::make('customer_phone')
                    ->label('Mijoz telefoni'),
                Tables\Columns\TextColumn::make('square_meters')
                    ->label('Kv. metr')
                    ->numeric(2),
                Tables\Columns\TextColumn::make('total_price')
                    ->label('Umumiy narx')
                    ->money('uzs', divideBy: 1),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state) => $state->label())
                    ->color(fn (OrderStatus $state) => match ($state) {
                        OrderStatus::New => 'gray',
                        OrderStatus::InProgress => 'warning',
                        OrderStatus::Done => 'success',
                        OrderStatus::Cancelled => 'danger',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Sana')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(OrderStatus::cases())->mapWithKeys(fn (OrderStatus $status) => [$status->value => $status->label()])),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Sanadan'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Sanagacha'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['created_from'], fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'], fn ($query, $date) => $query->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListOrders::route('/'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
