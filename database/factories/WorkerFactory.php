<?php

namespace Database\Factories;

use App\Enums\WorkerStatus;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Worker>
 */
class WorkerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'telegram_id' => fake()->unique()->numberBetween(100000000, 999999999),
            'ism' => fake()->name(),
            'telefon' => fake()->phoneNumber(),
            'status' => WorkerStatus::Pending,
        ];
    }
}
