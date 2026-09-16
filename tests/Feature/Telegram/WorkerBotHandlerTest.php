<?php

namespace Tests\Feature\Telegram;

use App\Enums\WorkerStatus;
use App\Models\Worker;
use DefStudio\Telegraph\Models\TelegraphBot;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WorkerBotHandlerTest extends TestCase
{
    use DatabaseTransactions;

    private TelegraphBot $bot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bot = TelegraphBot::create([
            'token' => 'test-bot-token-'.uniqid(),
            'name' => 'Test Bot',
        ]);

        Http::fake();
    }

    private function sendStart(int $telegramId, string $firstName = 'Test'): void
    {
        $this->postJson(route('telegraph.webhook', ['token' => $this->bot->token]), [
            'update_id' => random_int(1, 999999),
            'message' => [
                'message_id' => 1,
                'date' => now()->timestamp,
                'text' => '/start',
                'from' => [
                    'id' => $telegramId,
                    'is_bot' => false,
                    'first_name' => $firstName,
                    'username' => 'user'.$telegramId,
                ],
                'chat' => [
                    'id' => $telegramId,
                    'type' => 'private',
                    'first_name' => $firstName,
                ],
            ],
        ])->assertNoContent();
    }

    public function test_new_telegram_id_creates_pending_worker_and_sends_waiting_message(): void
    {
        $telegramId = random_int(100000000, 999999999);

        $this->assertDatabaseMissing('workers', ['telegram_id' => $telegramId]);

        $this->sendStart($telegramId, 'Yangi Ishchi');

        $this->assertDatabaseHas('workers', [
            'telegram_id' => $telegramId,
            'ism' => 'Yangi Ishchi',
            'status' => WorkerStatus::Pending->value,
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains($request['text'] ?? '', 'kuting');
        });
    }

    public function test_active_worker_sees_main_menu_with_new_order_button(): void
    {
        $worker = Worker::factory()->create([
            'status' => WorkerStatus::Active,
            'ism' => 'Faol Ishchi',
        ]);

        $this->sendStart($worker->telegram_id, 'Faol Ishchi');

        Http::assertSent(function ($request) {
            $keyboard = $request['reply_markup']['inline_keyboard'] ?? null;

            return str_contains($request->url(), 'sendMessage')
                && str_contains($request['text'] ?? '', 'Faol Ishchi')
                && $keyboard !== null
                && str_contains($keyboard[0][0]['text'] ?? '', 'Yangi buyurtma');
        });
    }

    public function test_pending_worker_sees_waiting_message(): void
    {
        $worker = Worker::factory()->create(['status' => WorkerStatus::Pending]);

        $this->sendStart($worker->telegram_id);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains($request['text'] ?? '', 'kuting');
        });
    }

    public function test_blocked_worker_sees_forbidden_message(): void
    {
        $worker = Worker::factory()->create(['status' => WorkerStatus::Blocked]);

        $this->sendStart($worker->telegram_id);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains($request['text'] ?? '', 'ruxsat berilmagan');
        });
    }
}
