<?php

namespace Tests\Feature\Filament;

use App\Enums\WorkerStatus;
use App\Filament\Widgets\OrderStatsOverview;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class OrderStatsOverviewTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Dev bazasida oldingi qo'lda sinovlardan/seed'dan qolgan
        // buyurtma/mijoz/ishchi yozuvlari statistikani buzmasligi uchun,
        // shu testga xos toza holatdan boshlaymiz. DatabaseTransactions
        // tufayli bu o'chirishlar test tugagach avtomatik bekor qilinadi -
        // haqiqiy ma'lumotlarga doimiy ta'sir qilmaydi.
        Order::query()->delete();
        Customer::query()->delete();
        Worker::query()->delete();
    }

    private function createOrderAt(Worker $worker, \DateTimeInterface $date, int $totalPrice): Order
    {
        $order = Order::factory()->create([
            'worker_id' => $worker->id,
            'total_price' => $totalPrice,
        ]);
        $order->forceFill(['created_at' => $date])->save();

        return $order;
    }

    public function test_todays_orders_stat_shows_correct_count_total_and_comparison_to_yesterday(): void
    {
        $admin = User::factory()->create();
        $worker = Worker::factory()->create(['status' => WorkerStatus::Active]);

        $this->createOrderAt($worker, now(), 100000);
        $this->createOrderAt($worker, now(), 100000);
        $this->createOrderAt($worker, now(), 100000);

        $this->createOrderAt($worker, now()->subDay(), 50000);

        $this->actingAs($admin);

        Livewire::test(OrderStatsOverview::class)
            ->assertSee('3 ta')
            ->assertSee('300 000')
            ->assertSee('+200% (kecha: 1 ta)');
    }

    public function test_this_month_orders_stat_shows_correct_count_total_and_comparison_to_last_month(): void
    {
        $admin = User::factory()->create();
        $worker = Worker::factory()->create(['status' => WorkerStatus::Active]);

        $thisMonth = now()->startOfMonth()->addDay();
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth()->addDay();

        $this->createOrderAt($worker, $thisMonth, 100000);
        $this->createOrderAt($worker, $thisMonth, 100000);
        $this->createOrderAt($worker, $thisMonth, 100000);
        $this->createOrderAt($worker, $thisMonth, 100000);

        $this->createOrderAt($worker, $lastMonth, 50000);
        $this->createOrderAt($worker, $lastMonth, 50000);

        $this->actingAs($admin);

        Livewire::test(OrderStatsOverview::class)
            ->assertSee('4 ta')
            ->assertSee('400 000')
            ->assertSee("+100% (o'tgan oy: 2 ta)");
    }

    public function test_total_customers_and_active_workers_are_counted_correctly(): void
    {
        $admin = User::factory()->create();

        Customer::factory()->count(4)->create();

        Worker::factory()->count(2)->create(['status' => WorkerStatus::Active]);
        Worker::factory()->count(1)->create(['status' => WorkerStatus::Pending]);
        Worker::factory()->count(1)->create(['status' => WorkerStatus::Blocked]);

        $this->actingAs($admin);

        Livewire::test(OrderStatsOverview::class)
            ->assertSee('4 ta')
            ->assertSee('2 ta')
            ->assertDontSee('3 ta');
    }

    public function test_zero_previous_period_is_described_without_a_misleading_percentage(): void
    {
        $admin = User::factory()->create();
        $worker = Worker::factory()->create(['status' => WorkerStatus::Active]);

        $this->createOrderAt($worker, now(), 100000);

        $this->actingAs($admin);

        Livewire::test(OrderStatsOverview::class)
            ->assertSee('yangi faollik (kecha: 0 ta)');
    }
}
