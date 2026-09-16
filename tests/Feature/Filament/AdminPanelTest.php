<?php

namespace Tests\Feature\Filament;

use App\Enums\WorkerStatus;
use App\Models\Order;
use App\Models\PriceSetting;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use DatabaseTransactions;

    public function test_login_page_loads(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    public function test_guest_is_redirected_from_admin_pages(): void
    {
        $this->get('/admin/workers')->assertRedirect('/admin/login');
    }

    public function test_authenticated_admin_can_see_resource_pages(): void
    {
        $admin = User::factory()->create();
        $priceSetting = PriceSetting::firstOrCreate([], [
            'price_per_sqm' => 100000,
            'updated_by' => $admin->id,
        ]);
        $worker = Worker::factory()->create(['status' => WorkerStatus::Active]);
        $order = Order::factory()->create(['worker_id' => $worker->id]);

        $this->actingAs($admin);

        $this->get('/admin/price-settings')->assertOk();
        $this->get("/admin/price-settings/{$priceSetting->id}/edit")->assertOk();

        $this->get('/admin/workers')->assertOk();
        $this->get("/admin/workers/{$worker->id}/edit")->assertOk();

        $this->get('/admin/orders')->assertOk();
        $this->get("/admin/orders/{$order->id}/edit")->assertOk();
    }

    public function test_updating_price_setting_logs_price_history(): void
    {
        $admin = User::factory()->create();
        $priceSetting = PriceSetting::firstOrCreate([], [
            'price_per_sqm' => 100000,
            'updated_by' => $admin->id,
        ]);
        $originalPrice = $priceSetting->price_per_sqm;

        $this->actingAs($admin);

        $priceSetting->update(['price_per_sqm' => $originalPrice + 1000, 'updated_by' => $admin->id]);

        $this->assertDatabaseHas('price_history', [
            'price_per_sqm' => $originalPrice,
            'changed_by' => $admin->id,
        ]);
    }
}
