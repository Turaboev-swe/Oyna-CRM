<?php

namespace Tests\Feature\Filament;

use App\Filament\Widgets\DailyOrdersChart;
use App\Models\Order;
use App\Models\Worker;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

class DailyOrdersChartTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Boshqa testlar/qo'lda sinovlardan qolgan buyurtmalar kunlik
        // guruhlashni buzmasligi uchun toza holatdan boshlanadi.
        Order::query()->delete();
    }

    private function getChartData(): array
    {
        $widget = new DailyOrdersChart;

        $method = new ReflectionMethod($widget, 'getData');
        $method->setAccessible(true);

        return $method->invoke($widget);
    }

    public function test_daily_counts_are_grouped_correctly_across_thirty_days(): void
    {
        $worker = Worker::factory()->create();

        $this->createOrderOnDate($worker, now(), 2);
        $this->createOrderOnDate($worker, now()->subDays(5), 3);
        $this->createOrderOnDate($worker, now()->subDays(29), 1);

        // 30 kundan tashqaridagi buyurtma hisobga kirmasligi kerak
        $this->createOrderOnDate($worker, now()->subDays(31), 5);

        $data = $this->getChartData();

        $this->assertCount(30, $data['labels']);
        $this->assertCount(30, $data['datasets'][0]['data']);

        $total = array_sum($data['datasets'][0]['data']);
        $this->assertSame(6, $total);

        $this->assertSame(2, $data['datasets'][0]['data'][29]);
        $this->assertSame(3, $data['datasets'][0]['data'][24]);
        $this->assertSame(1, $data['datasets'][0]['data'][0]);
    }

    public function test_days_without_orders_are_shown_as_zero(): void
    {
        $worker = Worker::factory()->create();

        $this->createOrderOnDate($worker, now(), 1);

        $data = $this->getChartData();

        $this->assertSame(0, $data['datasets'][0]['data'][0]);
        $this->assertSame(1, $data['datasets'][0]['data'][29]);
    }

    private function createOrderOnDate(Worker $worker, \DateTimeInterface $date, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $order = Order::factory()->create(['worker_id' => $worker->id]);
            $order->forceFill(['created_at' => $date])->save();
        }
    }
}
