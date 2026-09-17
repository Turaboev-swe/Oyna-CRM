<?php

namespace Tests\Feature\Telegram;

use App\Enums\OrderDraftStep;
use App\Enums\OrderStatus;
use App\Enums\WorkerStatus;
use App\Models\Customer;
use App\Models\PriceSetting;
use App\Models\User;
use App\Models\Worker;
use DefStudio\Telegraph\Models\TelegraphBot;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use DatabaseTransactions;

    private TelegraphBot $bot;

    private Worker $worker;

    private PriceSetting $priceSetting;

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
        $this->priceSetting = PriceSetting::firstOrCreate([], [
            'price_per_sqm' => 100000,
            'updated_by' => $admin->id,
        ]);
        $this->priceSetting->forceFill(['price_per_sqm' => 100000])->save();

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

    private function sendCallback(string $action): void
    {
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
                'data' => "action:{$action}",
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

    public function test_full_successful_flow_with_customer_creates_order(): void
    {
        $this->sendCallback('newOrder');
        $this->assertDatabaseHas('order_drafts', [
            'worker_id' => $this->worker->id,
            'step' => OrderDraftStep::AwaitingCalcChoice->value,
        ]);

        $this->sendCallback('calcBySquareMeters');
        $this->assertDatabaseHas('order_drafts', [
            'worker_id' => $this->worker->id,
            'step' => OrderDraftStep::AwaitingSquareMeters->value,
        ]);

        $this->sendText('12.5');
        $this->assertDatabaseHas('order_drafts', [
            'worker_id' => $this->worker->id,
            'step' => OrderDraftStep::AwaitingCustomerChoice->value,
            'square_meters' => 12.50,
        ]);

        $this->sendCallback('customerNew');
        $this->assertDatabaseHas('order_drafts', [
            'worker_id' => $this->worker->id,
            'step' => OrderDraftStep::AwaitingCustomerName->value,
        ]);

        $this->sendText('Aziz Azizov');
        $this->assertDatabaseHas('order_drafts', [
            'worker_id' => $this->worker->id,
            'step' => OrderDraftStep::AwaitingCustomerPhone->value,
            'customer_name' => 'Aziz Azizov',
        ]);

        $this->sendText('+998901112233');
        $this->assertDatabaseHas('order_drafts', [
            'worker_id' => $this->worker->id,
            'step' => OrderDraftStep::AwaitingConfirmation->value,
            'customer_phone' => '+998901112233',
        ]);
        $this->assertLastMessageContains('Jami: 1 250 000');

        $customer = Customer::where('phone', '+998901112233')->first();
        $this->assertNotNull($customer);
        $this->assertSame('Aziz Azizov', $customer->name);

        $this->sendCallback('confirmOrder');

        $this->assertDatabaseMissing('order_drafts', ['worker_id' => $this->worker->id]);
        $this->assertDatabaseHas('orders', [
            'worker_id' => $this->worker->id,
            'customer_id' => $customer->id,
            'customer_name' => 'Aziz Azizov',
            'customer_phone' => '+998901112233',
            'square_meters' => 12.50,
            'price_per_sqm_snapshot' => 100000,
            'total_price' => 1250000,
            'status' => OrderStatus::New->value,
        ]);
        $this->assertLastMessageContains('qabul qilindi');
    }

    public function test_flow_without_customer_creates_order_without_customer_info(): void
    {
        $this->sendCallback('newOrder');
        $this->sendCallback('calcBySquareMeters');
        $this->sendText('10');
        $this->sendCallback('customerNone');

        $this->assertDatabaseHas('order_drafts', [
            'worker_id' => $this->worker->id,
            'step' => OrderDraftStep::AwaitingConfirmation->value,
            'customer_name' => null,
            'customer_phone' => null,
        ]);
        $this->assertLastMessageContains('kiritilmagan');

        $this->sendCallback('confirmOrder');

        $this->assertDatabaseHas('orders', [
            'worker_id' => $this->worker->id,
            'customer_name' => null,
            'customer_phone' => null,
            'square_meters' => 10.00,
            'total_price' => 1000000,
        ]);
        $this->assertDatabaseMissing('order_drafts', ['worker_id' => $this->worker->id]);
    }

    public function test_cancel_during_flow_discards_draft_and_creates_no_order(): void
    {
        $this->sendCallback('newOrder');
        $this->sendCallback('calcBySquareMeters');
        $this->sendText('20');

        $this->assertDatabaseHas('order_drafts', ['worker_id' => $this->worker->id]);

        $this->sendCallback('cancelOrder');

        $this->assertDatabaseMissing('order_drafts', ['worker_id' => $this->worker->id]);
        $this->assertDatabaseMissing('orders', ['worker_id' => $this->worker->id]);
        $this->assertLastMessageContains('Bekor qilindi');
    }

    public function test_invalid_square_meters_input_is_rejected_and_step_unchanged(): void
    {
        $this->sendCallback('newOrder');
        $this->sendCallback('calcBySquareMeters');
        $this->sendText('not-a-number');

        $this->assertDatabaseHas('order_drafts', [
            'worker_id' => $this->worker->id,
            'step' => OrderDraftStep::AwaitingSquareMeters->value,
            'square_meters' => null,
        ]);
        $this->assertLastMessageContains("Noto'g'ri format");
    }

    public function test_inactive_worker_cannot_start_order(): void
    {
        $this->worker->update(['status' => WorkerStatus::Pending]);

        $this->sendCallback('newOrder');

        $this->assertDatabaseMissing('order_drafts', ['worker_id' => $this->worker->id]);
    }

    public function test_full_successful_flow_with_dimensions_creates_order(): void
    {
        $this->sendCallback('newOrder');
        $this->sendCallback('calcByDimensions');
        $this->assertDatabaseHas('order_drafts', [
            'worker_id' => $this->worker->id,
            'step' => OrderDraftStep::AwaitingWidth->value,
        ]);

        $this->sendText('1.5');
        $this->assertDatabaseHas('order_drafts', [
            'worker_id' => $this->worker->id,
            'step' => OrderDraftStep::AwaitingHeight->value,
            'width_meters' => 1.50,
        ]);

        $this->sendText('2.0');
        $this->assertDatabaseHas('order_drafts', [
            'worker_id' => $this->worker->id,
            'step' => OrderDraftStep::AwaitingCustomerChoice->value,
            'width_meters' => 1.50,
            'height_meters' => 2.00,
            'square_meters' => 3.00,
        ]);
        $this->assertLastMessageContains('Hisoblangan maydon: 3');

        $this->sendCallback('customerNone');
        $this->sendCallback('confirmOrder');

        $this->assertDatabaseHas('orders', [
            'worker_id' => $this->worker->id,
            'square_meters' => 3.00,
            'price_per_sqm_snapshot' => 100000,
            'total_price' => 300000,
        ]);
        $this->assertDatabaseMissing('order_drafts', ['worker_id' => $this->worker->id]);
    }

    public function test_dimensions_area_is_rounded_correctly(): void
    {
        $this->sendCallback('newOrder');
        $this->sendCallback('calcByDimensions');

        $this->sendText('1.33');
        $this->sendText('2.17');

        $this->assertDatabaseHas('order_drafts', [
            'worker_id' => $this->worker->id,
            'width_meters' => 1.33,
            'height_meters' => 2.17,
            'square_meters' => 2.89,
        ]);
        $this->assertLastMessageContains('Hisoblangan maydon: 2.89');
    }

    public function test_invalid_width_input_is_rejected_and_step_unchanged(): void
    {
        $this->sendCallback('newOrder');
        $this->sendCallback('calcByDimensions');

        $this->sendText('not-a-number');

        $this->assertDatabaseHas('order_drafts', [
            'worker_id' => $this->worker->id,
            'step' => OrderDraftStep::AwaitingWidth->value,
            'width_meters' => null,
        ]);
        $this->assertLastMessageContains("Noto'g'ri format");
    }

    public function test_invalid_height_input_is_rejected_and_step_unchanged(): void
    {
        $this->sendCallback('newOrder');
        $this->sendCallback('calcByDimensions');
        $this->sendText('1.5');

        $this->sendText('not-a-number');

        $this->assertDatabaseHas('order_drafts', [
            'worker_id' => $this->worker->id,
            'step' => OrderDraftStep::AwaitingHeight->value,
            'width_meters' => 1.50,
            'height_meters' => null,
        ]);
        $this->assertLastMessageContains("Noto'g'ri format");
    }

    public function test_cancel_during_dimensions_step_discards_draft(): void
    {
        $this->sendCallback('newOrder');
        $this->sendCallback('calcByDimensions');
        $this->sendText('1.5');

        $this->assertDatabaseHas('order_drafts', [
            'worker_id' => $this->worker->id,
            'step' => OrderDraftStep::AwaitingHeight->value,
        ]);

        $this->sendCallback('cancelOrder');

        $this->assertDatabaseMissing('order_drafts', ['worker_id' => $this->worker->id]);
        $this->assertDatabaseMissing('orders', ['worker_id' => $this->worker->id]);
        $this->assertLastMessageContains('Bekor qilindi');
    }
}
