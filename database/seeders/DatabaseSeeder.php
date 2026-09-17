<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\WorkerStatus;
use App\Models\Order;
use App\Models\PriceSetting;
use App\Models\User;
use App\Models\Worker;
use DefStudio\Telegraph\Models\TelegraphBot;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@oynarom.uz'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
            ]
        );

        PriceSetting::firstOrCreate([], [
            'price_per_sqm' => 150000,
            'updated_by' => $admin->id,
        ]);

        $worker = Worker::firstOrCreate(
            ['telegram_id' => 123456789],
            [
                'ism' => 'Akmal Karimov',
                'telefon' => '+998901234567',
                'status' => WorkerStatus::Active,
            ]
        );

        Order::firstOrCreate(
            ['worker_id' => $worker->id, 'customer_name' => 'Test Mijoz'],
            [
                'customer_phone' => '+998907654321',
                'square_meters' => 12.5,
                'price_per_sqm_snapshot' => 150000,
                'total_price' => 1875000,
                'status' => OrderStatus::New,
            ]
        );

        if (filled($token = config('telegraph.bot_token'))) {
            TelegraphBot::firstOrCreate(
                ['token' => $token],
                ['name' => 'Oyna-Rom Bot']
            );
        }
    }
}
