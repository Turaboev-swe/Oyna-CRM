<?php

namespace Tests\Feature\Telegram;

use App\Enums\OrderDraftStep;
use App\Enums\WorkerStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PriceSetting;
use App\Models\User;
use App\Models\Worker;
use DefStudio\Telegraph\Models\TelegraphBot;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CustomerSelectionTest extends TestCase
{
    use DatabaseTransactions;

    private TelegraphBot $bot;

    private Worker $worker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bot = TelegraphBot::create([
            'token' => 'test-bot-token-'.uniqid(),
            'name' => 'Test Bot',
        ]);

        $this->worker = Worker::factory()->create([
            'status' => WorkerStatus::Active,
            'ism' => 'Faol Ishchi',
        ]);

        $admin = User::factory()->create();
        $priceSetting = PriceSetting::firstOrCreate([], [
            'price_per_sqm' => 100000,
            'updated_by' => $admin->id,
        ]);
        $priceSetting->forceFill(['price_per_sqm' => 100000])->save();

        Http::fake();
    }

    private function sendText(string $text): void
    {
        $this->postJson(route('telegraph.webhook', ['token' => $this->bot->token]), [
            'update_id' => random_int(1, 999999),
            'message' => [
                'message_id' => random_int(1, 999999),
                'date' => now()->timestamp,
                'text' => $text,
                'from' => [
                    'id' => $this->worker->telegram_id,
                    'is_bot' => false,
                    'first_name' => $this->worker->ism,
                ],
                'chat' => [
                    'id' => $this->worker->telegram_id,
                    'type' => 'private',
                    'first_name' => $this->worker->ism,
                ],
            ],
        ])->assertNoContent();
    }

    private function sendCallback(string $action, array $params = []): void
    {
        $data = collect(array_merge(['action' => $action], $params))
            ->map(fn ($value, $key) => "{$key}:{$value}")
            ->implode(';');

        $this->postJson(route('telegraph.webhook', ['token' => $this->bot->token]), [
            'update_id' => random_int(1, 999999),
            'callback_query' => [
                'id' => random_int(1, 999999),
                'from' => [
                    'id' => $this->worker->telegram_id,
                    'is_bot' => false,
                    'first_name' => $this->worker->ism,
                ],
                'message' => [
                    'message_id' => random_int(1, 999999),
                    'date' => now()->timestamp,
                    'text' => 'placeholder',
                    'chat' => [
                        'id' => $this->worker->telegram_id,
                        'type' => 'private',
                        'first_name' => $this->worker->ism,
                    ],
                ],
                'data' => $data,
            ],
        ])->assertNoContent();
    }

    private function assertLastMessageContains(string $needle): void
    {
        Http::assertSent(function ($request) use ($needle) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains($request['text'] ?? '', $needle);
        });
    }

    private function lastKeyboardButtonTexts(): array
    {
        $texts = [];

        foreach (Http::recorded() as [$request]) {
            if (str_contains($request->url(), 'sendMessage')) {
                $keyboard = $request['reply_markup']['inline_keyboard'] ?? [];
                $texts = collect($keyboard)->flatten(1)->pluck('text')->all();
            }
        }

        return $texts;
    }

    private function startCustomerSelection(): void
    {
        $this->sendCallback('newOrder');
        $this->sendCallback('calcBySquareMeters');
        $this->sendText('12.5');
        $this->sendCallback('customerExisting');
    }

    public function test_selecting_existing_customer_autofills_name_and_phone(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Oldindan Mavjud',
            'phone' => '+998900000001',
        ]);

        $this->startCustomerSelection();
        $this->assertDatabaseHas('order_drafts', [
            'worker_id' => $this->worker->id,
            'step' => OrderDraftStep::AwaitingCustomerSelection->value,
        ]);

        $this->sendCallback('selectCustomer', ['id' => $customer->id]);

        $this->assertDatabaseHas('order_drafts', [
            'worker_id' => $this->worker->id,
            'step' => OrderDraftStep::AwaitingConfirmation->value,
            'customer_id' => $customer->id,
            'customer_name' => 'Oldindan Mavjud',
            'customer_phone' => '+998900000001',
        ]);
        $this->assertLastMessageContains('Oldindan Mavjud');
    }

    public function test_duplicate_phone_links_to_existing_customer_instead_of_creating_new(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Ro\'yxatdagi Ism',
            'phone' => '+998900000002',
        ]);

        $this->sendCallback('newOrder');
        $this->sendCallback('calcBySquareMeters');
        $this->sendText('12.5');
        $this->sendCallback('customerNew');
        $this->sendText('Ishchi Kiritgan Ism');
        $this->sendText('+998900000002');

        $this->assertSame(1, Customer::where('phone', '+998900000002')->count());

        $this->assertDatabaseHas('order_drafts', [
            'worker_id' => $this->worker->id,
            'step' => OrderDraftStep::AwaitingConfirmation->value,
            'customer_id' => $customer->id,
            'customer_name' => "Ro'yxatdagi Ism",
        ]);
        $this->assertLastMessageContains('allaqachon ro\'yxatda');

        $this->sendCallback('confirmOrder');

        $this->assertDatabaseHas('orders', [
            'worker_id' => $this->worker->id,
            'customer_id' => $customer->id,
            'customer_name' => "Ro'yxatdagi Ism",
        ]);
    }

    public function test_new_customer_without_duplicate_creates_new_customer_record(): void
    {
        $this->sendCallback('newOrder');
        $this->sendCallback('calcBySquareMeters');
        $this->sendText('12.5');
        $this->sendCallback('customerNew');
        $this->sendText('Yangi Mijoz');
        $this->sendText('+998900000003');

        $customer = Customer::where('phone', '+998900000003')->first();

        $this->assertNotNull($customer);
        $this->assertSame('Yangi Mijoz', $customer->name);
        $this->assertDatabaseHas('order_drafts', [
            'worker_id' => $this->worker->id,
            'customer_id' => $customer->id,
        ]);
    }

    public function test_customer_list_pagination_shows_next_and_previous_correctly(): void
    {
        Customer::factory()->count(7)->create();

        $this->startCustomerSelection();

        $firstPageButtons = $this->lastKeyboardButtonTexts();
        $this->assertCount(7, Customer::all());
        $this->assertTrue(collect($firstPageButtons)->contains(fn ($text) => str_contains($text, 'Keyingisi')));
        $this->assertFalse(collect($firstPageButtons)->contains(fn ($text) => str_contains($text, 'Oldingisi')));
        // 5 mijoz + "Keyingisi" + "Bekor qilish" = 7 tugma
        $this->assertCount(7, $firstPageButtons);

        $this->sendCallback('customersPage', ['page' => 2]);

        $secondPageButtons = $this->lastKeyboardButtonTexts();
        $this->assertTrue(collect($secondPageButtons)->contains(fn ($text) => str_contains($text, 'Oldingisi')));
        $this->assertFalse(collect($secondPageButtons)->contains(fn ($text) => str_contains($text, 'Keyingisi')));
        // qolgan 2 mijoz + "Oldingisi" + "Bekor qilish" = 4 tugma
        $this->assertCount(4, $secondPageButtons);
    }

    public function test_customer_list_sorts_most_recently_ordered_customer_first(): void
    {
        $customerWithOldOrder = Customer::factory()->create(['name' => 'Eski Buyurtma']);
        $customerWithRecentOrder = Customer::factory()->create(['name' => 'Yangi Buyurtma']);
        $customerWithoutOrders = Customer::factory()->create(['name' => 'Buyurtmasiz']);

        $otherWorker = Worker::factory()->create(['status' => WorkerStatus::Active]);

        $oldOrder = Order::factory()->create([
            'worker_id' => $otherWorker->id,
            'customer_id' => $customerWithOldOrder->id,
        ]);
        $oldOrder->forceFill(['created_at' => now()->subDays(5)])->save();

        Order::factory()->create([
            'worker_id' => $otherWorker->id,
            'customer_id' => $customerWithRecentOrder->id,
        ]);

        $this->startCustomerSelection();

        $buttons = $this->lastKeyboardButtonTexts();
        $order = collect($buttons)->values();

        $recentIndex = $order->search(fn ($text) => str_contains($text, 'Yangi Buyurtma'));
        $oldIndex = $order->search(fn ($text) => str_contains($text, 'Eski Buyurtma'));
        $noOrderIndex = $order->search(fn ($text) => str_contains($text, 'Buyurtmasiz'));

        $this->assertNotFalse($recentIndex);
        $this->assertNotFalse($oldIndex);
        $this->assertNotFalse($noOrderIndex);
        $this->assertTrue($recentIndex < $oldIndex);
        $this->assertTrue($oldIndex < $noOrderIndex);
    }
}
