<?php

namespace App\Filament\Widgets;

use App\Models\Worker;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopWorkersWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Shu oyning eng faol ishchilari';

    public function table(Table $table): Table
    {
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        return $table
            ->query(
                Worker::query()
                    ->withCount(['orders as orders_count' => fn ($query) => $query->whereBetween('created_at', [$start, $end])])
                    ->withSum(['orders as orders_total' => fn ($query) => $query->whereBetween('created_at', [$start, $end])], 'total_price')
                    ->having('orders_count', '>', 0)
                    ->orderByDesc('orders_count')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('ism')
                    ->label('Ishchi'),
                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Buyurtmalar soni'),
                Tables\Columns\TextColumn::make('orders_total')
                    ->label('Jami summa')
                    ->money('uzs', divideBy: 1),
            ])
            ->paginated(false);
    }
}
