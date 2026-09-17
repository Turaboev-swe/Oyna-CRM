<?php

namespace Database\Factories;

use App\Enums\OrderDraftStep;
use App\Models\OrderDraft;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderDraft>
 */
class OrderDraftFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'worker_id' => Worker::factory(),
            'step' => OrderDraftStep::AwaitingSquareMeters,
        ];
    }
}
