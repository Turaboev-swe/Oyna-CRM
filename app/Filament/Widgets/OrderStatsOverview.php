<?php

namespace App\Filament\Widgets;

use App\Enums\WorkerStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Worker;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class OrderStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $today = $this->periodStats(now()->startOfDay(), now()->endOfDay());
        $yesterday = $this->periodStats(now()->subDay()->startOfDay(), now()->subDay()->endOfDay());

        $thisMonth = $this->periodStats(now()->startOfMonth(), now()->endOfMonth());
        $lastMonth = $this->periodStats(
            now()->subMonthNoOverflow()->startOfMonth(),
            now()->subMonthNoOverflow()->endOfMonth(),
        );

        return [
            Stat::make('Bugungi buyurtmalar', "{$today['count']} ta")
                ->description($this->formatMoney($today['total'])." so'm — ".$this->changeDescription($today['count'], $yesterday['count'], 'kecha'))
                ->descriptionIcon($this->changeIcon($today['count'], $yesterday['count']), IconPosition::Before)
                ->color($this->changeColor($today['count'], $yesterday['count'])),

            Stat::make('Shu oylik buyurtmalar', "{$thisMonth['count']} ta")
                ->description($this->formatMoney($thisMonth['total'])." so'm — ".$this->changeDescription($thisMonth['count'], $lastMonth['count'], "o'tgan oy"))
                ->descriptionIcon($this->changeIcon($thisMonth['count'], $lastMonth['count']), IconPosition::Before)
                ->color($this->changeColor($thisMonth['count'], $lastMonth['count'])),

            Stat::make('Jami mijozlar', Customer::count().' ta')
                ->icon('heroicon-o-user-group')
                ->color('gray'),

            Stat::make('Faol ishchilar', Worker::where('status', WorkerStatus::Active)->count().' ta')
                ->icon('heroicon-o-users')
                ->color('gray'),
        ];
    }

    /**
     * @return array{count: int, total: float}
     */
    private function periodStats(Carbon $from, Carbon $to): array
    {
        $result = Order::whereBetween('created_at', [$from, $to])
            ->selectRaw('COUNT(*) as aggregated_count, COALESCE(SUM(total_price), 0) as aggregated_total')
            ->first();

        return [
            'count' => (int) $result->aggregated_count,
            'total' => (float) $result->aggregated_total,
        ];
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 0, '.', ' ');
    }

    private function changeDescription(int $current, int $previous, string $previousLabel): string
    {
        if ($previous === 0) {
            return $current > 0
                ? "yangi faollik ({$previousLabel}: 0 ta)"
                : "{$previousLabel}: 0 ta";
        }

        $percent = (int) round((($current - $previous) / $previous) * 100);
        $sign = $percent > 0 ? '+' : '';

        return "{$sign}{$percent}% ({$previousLabel}: {$previous} ta)";
    }

    private function changeIcon(int $current, int $previous): string
    {
        return match (true) {
            $current > $previous => 'heroicon-m-arrow-trending-up',
            $current < $previous => 'heroicon-m-arrow-trending-down',
            default => 'heroicon-m-minus',
        };
    }

    private function changeColor(int $current, int $previous): string
    {
        return match (true) {
            $current > $previous => 'success',
            $current < $previous => 'danger',
            default => 'gray',
        };
    }
}
