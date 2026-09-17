<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;

class DailyOrdersChart extends ChartWidget
{
    protected static ?string $heading = "Kunlik buyurtmalar (so'nggi 30 kun)";

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $start = now()->subDays(29)->startOfDay();

        $counts = Order::query()
            ->selectRaw('DATE(created_at) as order_date, COUNT(*) as total')
            ->where('created_at', '>=', $start)
            ->groupBy('order_date')
            ->pluck('total', 'order_date');

        $labels = [];
        $data = [];

        for ($date = $start->copy(); $date->lte(now()->endOfDay()); $date->addDay()) {
            $labels[] = $date->format('d.m');
            $data[] = (int) ($counts[$date->format('Y-m-d')] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Buyurtmalar soni',
                    'data' => $data,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
