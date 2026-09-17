<?php

namespace App\Telegram\Handlers;

use App\Enums\OrderDraftStep;
use App\Enums\OrderStatus;
use App\Enums\WorkerStatus;
use App\Models\Order;
use App\Models\OrderDraft;
use App\Models\PriceSetting;
use App\Models\Worker;
use DefStudio\Telegraph\DTO\User as TelegramUser;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Stringable;

class WorkerBotHandler extends WebhookHandler
{
    public function start(string $parameter = ''): void
    {
        $telegramUser = $this->message?->from();

        if ($telegramUser === null) {
            return;
        }

        $worker = Worker::firstOrNew(['telegram_id' => $telegramUser->id()]);

        if (! $worker->exists) {
            $worker->fill([
                'ism' => $this->resolveName($telegramUser),
                'status' => WorkerStatus::Pending,
            ])->save();

            $this->chat->html('Arizangiz qabul qilindi. Admin tasdiqlashini kuting.')->send();

            return;
        }

        $this->sendStatusMessage($worker);
    }

    protected function handleChatMessage(Stringable $text): void
    {
        $worker = $this->currentWorker();

        if ($worker === null || $worker->status !== WorkerStatus::Active) {
            return;
        }

        $draft = $worker->orderDraft;

        if ($draft === null) {
            return;
        }

        $value = trim((string) $text);

        match ($draft->step) {
            OrderDraftStep::AwaitingSquareMeters => $this->handleSquareMetersInput($draft, $value),
            OrderDraftStep::AwaitingCustomerName => $this->handleCustomerNameInput($draft, $value),
            OrderDraftStep::AwaitingCustomerPhone => $this->handleCustomerPhoneInput($draft, $value),
            OrderDraftStep::AwaitingHasCustomer, OrderDraftStep::AwaitingConfirmation => $this->chat->html('Iltimos, quyidagi tugmalardan birini tanlang.')->send(),
        };
    }

    public function newOrder(): void
    {
        $worker = $this->currentWorker();

        if ($worker === null || $worker->status !== WorkerStatus::Active) {
            return;
        }

        OrderDraft::updateOrCreate(
            ['worker_id' => $worker->id],
            [
                'step' => OrderDraftStep::AwaitingSquareMeters,
                'square_meters' => null,
                'customer_name' => null,
                'customer_phone' => null,
            ]
        );

        $this->chat
            ->html('Necha kvadrat metr? (masalan: 12.5)')
            ->keyboard($this->cancelKeyboard())
            ->send();
    }

    public function customerYes(): void
    {
        $draft = $this->draftAtStep(OrderDraftStep::AwaitingHasCustomer);

        if ($draft === null) {
            return;
        }

        $draft->update(['step' => OrderDraftStep::AwaitingCustomerName]);

        $this->chat
            ->html('Mijozning ismini kiriting:')
            ->keyboard($this->cancelKeyboard())
            ->send();
    }

    public function customerNo(): void
    {
        $draft = $this->draftAtStep(OrderDraftStep::AwaitingHasCustomer);

        if ($draft === null) {
            return;
        }

        $draft->update([
            'step' => OrderDraftStep::AwaitingConfirmation,
            'customer_name' => null,
            'customer_phone' => null,
        ]);

        $this->sendSummary($draft);
    }

    public function confirmOrder(): void
    {
        $worker = $this->currentWorker();
        $draft = $this->draftAtStep(OrderDraftStep::AwaitingConfirmation, $worker);

        if ($worker === null || $draft === null) {
            return;
        }

        $priceSetting = PriceSetting::first();

        if ($priceSetting === null) {
            $this->chat->html("Narx hali belgilanmagan. Iltimos, admin bilan bog'laning.")->send();

            return;
        }

        Order::create([
            'worker_id' => $worker->id,
            'customer_name' => $draft->customer_name,
            'customer_phone' => $draft->customer_phone,
            'square_meters' => $draft->square_meters,
            'price_per_sqm_snapshot' => $priceSetting->price_per_sqm,
            'total_price' => (float) $draft->square_meters * (float) $priceSetting->price_per_sqm,
            'status' => OrderStatus::New,
        ]);

        $draft->delete();

        $this->chat->html('✅ Buyurtma qabul qilindi!')->send();
        $this->sendMainMenu($worker);
    }

    public function cancelOrder(): void
    {
        $worker = $this->currentWorker();

        $worker?->orderDraft?->delete();

        $this->chat->html('Bekor qilindi.')->send();

        if ($worker !== null) {
            $this->sendMainMenu($worker);
        }
    }

    private function handleSquareMetersInput(OrderDraft $draft, string $value): void
    {
        $squareMeters = $this->parseSquareMeters($value);

        if ($squareMeters === null) {
            $this->chat
                ->html("Noto'g'ri format. Iltimos, raqam kiriting (masalan: 12.5)")
                ->keyboard($this->cancelKeyboard())
                ->send();

            return;
        }

        $draft->update([
            'square_meters' => $squareMeters,
            'step' => OrderDraftStep::AwaitingHasCustomer,
        ]);

        $keyboard = Keyboard::make()
            ->row([
                Button::make('✅ Ha')->action('customerYes'),
                Button::make("❌ Yo'q")->action('customerNo'),
            ])
            ->row([$this->cancelButton()]);

        $this->chat->html("Mijoz ma'lumoti bormi?")->keyboard($keyboard)->send();
    }

    private function handleCustomerNameInput(OrderDraft $draft, string $value): void
    {
        if ($value === '') {
            $this->chat->html('Iltimos, mijozning ismini kiriting:')->keyboard($this->cancelKeyboard())->send();

            return;
        }

        $draft->update([
            'customer_name' => $value,
            'step' => OrderDraftStep::AwaitingCustomerPhone,
        ]);

        $this->chat->html('Mijozning telefon raqamini kiriting:')->keyboard($this->cancelKeyboard())->send();
    }

    private function handleCustomerPhoneInput(OrderDraft $draft, string $value): void
    {
        if ($value === '') {
            $this->chat->html('Iltimos, telefon raqamini kiriting:')->keyboard($this->cancelKeyboard())->send();

            return;
        }

        $draft->update([
            'customer_phone' => $value,
            'step' => OrderDraftStep::AwaitingConfirmation,
        ]);

        $this->sendSummary($draft);
    }

    private function parseSquareMeters(string $value): ?float
    {
        $normalized = str_replace(',', '.', trim($value));

        if (! is_numeric($normalized)) {
            return null;
        }

        $number = (float) $normalized;

        if ($number <= 0) {
            return null;
        }

        return round($number, 2);
    }

    private function sendSummary(OrderDraft $draft): void
    {
        $customer = $draft->customer_name !== null
            ? "{$draft->customer_name} ({$draft->customer_phone})"
            : 'kiritilmagan';

        $priceSetting = PriceSetting::first();
        $total = $priceSetting !== null
            ? number_format((float) $draft->square_meters * (float) $priceSetting->price_per_sqm, 0, '.', ' ')
            : '?';

        $message = "📋 Buyurtma:\nKv.metr: {$draft->square_meters}\nMijoz: {$customer}\nJami: {$total} so'm";

        $keyboard = Keyboard::make()->row([
            Button::make('✅ Tasdiqlash')->action('confirmOrder'),
            Button::make('❌ Bekor qilish')->action('cancelOrder'),
        ]);

        $this->chat->html($message)->keyboard($keyboard)->send();
    }

    private function draftAtStep(OrderDraftStep $step, ?Worker $worker = null): ?OrderDraft
    {
        $worker ??= $this->currentWorker();

        if ($worker === null) {
            return null;
        }

        $draft = $worker->orderDraft;

        if ($draft === null || $draft->step !== $step) {
            return null;
        }

        return $draft;
    }

    private function currentWorker(): ?Worker
    {
        return Worker::where('telegram_id', $this->chat->chat_id)->first();
    }

    private function cancelButton(): Button
    {
        return Button::make('❌ Bekor qilish')->action('cancelOrder');
    }

    private function cancelKeyboard(): Keyboard
    {
        return Keyboard::make()->row([$this->cancelButton()]);
    }

    private function sendStatusMessage(Worker $worker): void
    {
        match ($worker->status) {
            WorkerStatus::Active => $this->sendMainMenu($worker),
            WorkerStatus::Pending => $this->chat->html('Hali tasdiqlanmagansiz, iltimos kuting.')->send(),
            WorkerStatus::Blocked => $this->chat->html('Sizga ruxsat berilmagan.')->send(),
        };
    }

    private function resolveName(TelegramUser $telegramUser): string
    {
        $name = trim($telegramUser->firstName().' '.$telegramUser->lastName());

        if ($name !== '') {
            return $name;
        }

        if ($telegramUser->username() !== '') {
            return $telegramUser->username();
        }

        return 'Ishchi';
    }

    private function sendMainMenu(Worker $worker): void
    {
        $keyboard = Keyboard::make()->row([
            Button::make('🆕 Yangi buyurtma')->action('newOrder'),
        ]);

        $this->chat
            ->html("Assalomu alaykum, {$worker->ism}! Quyidagi menyudan foydalaning:")
            ->keyboard($keyboard)
            ->send();
    }
}
