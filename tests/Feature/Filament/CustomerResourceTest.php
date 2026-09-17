<?php

namespace Tests\Feature\Filament;

use App\Enums\WorkerStatus;
use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerResourceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_orders_count_column_reflects_actual_number_of_orders(): void
    {
        $admin = User::factory()->create();
        $worker = Worker::factory()->create(['status' => WorkerStatus::Active]);

        $customerWithOrders = Customer::factory()->create();
        Order::factory()->count(3)->create([
            'worker_id' => $worker->id,
            'customer_id' => $customerWithOrders->id,
        ]);

        $customerWithoutOrders = Customer::factory()->create();

        $this->actingAs($admin);

        Livewire::test(ListCustomers::class)
            ->assertTableColumnStateSet('orders_count', 3, $customerWithOrders->getKey())
            ->assertTableColumnStateSet('orders_count', 0, $customerWithoutOrders->getKey());
    }

    public function test_customers_table_is_searchable_by_name_and_phone(): void
    {
        $admin = User::factory()->create();

        $match = Customer::factory()->create(['name' => 'Findable Name', 'phone' => '+998901234567']);
        $noMatch = Customer::factory()->create(['name' => 'Someone Else', 'phone' => '+998900000000']);

        $this->actingAs($admin);

        Livewire::test(ListCustomers::class)
            ->searchTable('Findable')
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$noMatch]);

        Livewire::test(ListCustomers::class)
            ->searchTable('+998901234567')
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$noMatch]);
    }
}
