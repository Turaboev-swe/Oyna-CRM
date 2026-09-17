<?php

namespace Tests\Feature\Filament;

use App\Enums\WorkerStatus;
use App\Filament\Widgets\TopWorkersWidget;
use App\Models\Order;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class TopWorkersWidgetTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Dev bazasidagi eski ma'lumotlar bu testning tartiblash
        // tekshiruviga aralashmasligi uchun toza holatdan boshlanadi.
        // DatabaseTransactions bu o'chirishlarni test oxirida bekor qiladi.
        Order::query()->delete();
        Worker::query()->delete();
    }

    public function test_workers_are_ordered_by_order_count_descending(): void
    {
        $admin = User::factory()->create();

        $topWorker = Worker::factory()->create(['ism' => 'Eng Faol']);
        $middleWorker = Worker::factory()->create(['ism' => "O'rtacha"]);
        $lastWorker = Worker::factory()->create(['ism' => 'Kamroq Faol']);

        Order::factory()->count(5)->create(['worker_id' => $topWorker->id]);
        Order::factory()->count(3)->create(['worker_id' => $middleWorker->id]);
        Order::factory()->count(1)->create(['worker_id' => $lastWorker->id]);

        $this->actingAs($admin);

        Livewire::test(TopWorkersWidget::class)
            ->assertCanSeeTableRecords([$topWorker, $middleWorker, $lastWorker], inOrder: true);
    }

    public function test_only_current_month_orders_count_towards_the_ranking(): void
    {
        $admin = User::factory()->create();

        $thisMonthWorker = Worker::factory()->create(['ism' => 'Shu Oy']);
        $lastMonthWorker = Worker::factory()->create(['ism' => "O'tgan Oy"]);

        Order::factory()->count(3)->create(['worker_id' => $thisMonthWorker->id]);

        $lastMonthOrder = Order::factory()->create(['worker_id' => $lastMonthWorker->id]);
        $lastMonthOrder->forceFill(['created_at' => now()->subMonthNoOverflow()])->save();

        $this->actingAs($admin);

        Livewire::test(TopWorkersWidget::class)
            ->assertCanSeeTableRecords([$thisMonthWorker])
            ->assertCanNotSeeTableRecords([$lastMonthWorker]);
    }

    public function test_workers_without_any_orders_this_month_are_not_shown(): void
    {
        $admin = User::factory()->create();

        $activeWorker = Worker::factory()->create(['status' => WorkerStatus::Active]);
        $inactiveWorker = Worker::factory()->create(['status' => WorkerStatus::Active]);

        Order::factory()->create(['worker_id' => $activeWorker->id]);

        $this->actingAs($admin);

        Livewire::test(TopWorkersWidget::class)
            ->assertCanSeeTableRecords([$activeWorker])
            ->assertCanNotSeeTableRecords([$inactiveWorker]);
    }

    public function test_only_top_ten_workers_are_shown(): void
    {
        $admin = User::factory()->create();

        $workers = Worker::factory()->count(12)->create();

        foreach ($workers as $index => $worker) {
            Order::factory()->count($index + 1)->create(['worker_id' => $worker->id]);
        }

        $this->actingAs($admin);

        $component = Livewire::test(TopWorkersWidget::class);
        $records = $component->instance()->getTableRecords();

        $this->assertCount(10, $records);
    }
}
