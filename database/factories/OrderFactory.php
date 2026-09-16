<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $squareMeters = fake()->randomFloat(2, 1, 50);
        $pricePerSqm = 150000;

        return [
            'worker_id' => Worker::factory(),
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->phoneNumber(),
            'square_meters' => $squareMeters,
            'price_per_sqm_snapshot' => $pricePerSqm,
            'total_price' => $squareMeters * $pricePerSqm,
            'status' => OrderStatus::New,
        ];
    }
}
