<?php

namespace App\Telegram\Handlers;

use App\Enums\OrderDraftStep;
use App\Enums\OrderStatus;
use App\Enums\WorkerStatus;
use App\Models\Customer;
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
    private const CUSTOMERS_PER_PAGE = 5;

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
            OrderDraftStep::AwaitingWidth => $this->handleWidthInput($draft, $value),
            OrderDraftStep::AwaitingHeight => $this->handleHeightInput($draft, $value),
            OrderDraftStep::AwaitingCustomerName => $this->handleCustomerNameInput($draft, $value),
            OrderDraftStep::AwaitingCustomerPhone => $this->handleCustomerPhoneInput($draft, $value),
            OrderDraftStep::AwaitingCalcChoice,
            OrderDraftStep::AwaitingCustomerChoice,
            OrderDraftStep::AwaitingCustomerSelection,
            OrderDraftStep::AwaitingConfirmation => $this->chat->html('Iltimos, quyidagi tugmalardan birini tanlang.')->send(),
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
                'step' => OrderDraftStep::AwaitingCalcChoice,
                'square_meters' => null,
                'width_meters' => null,
                'height_meters' => null,
                'customer_id' => null,
                'customer_name' => null,
                'customer_phone' => null,
            ]
        );

        $keyboard = Keyboard::make()
            ->row([Button::make('📐 Kvadrat metrni bilaman')->action('calcBySquareMeters')])
            ->row([Button::make("📏 Eni va bo'yini kiritaman")->action('calcByDimensions')])
            ->row([$this->cancelButton()]);

        $this->chat->html('Kvadrat metrni qanday kiritmoqchisiz?')->keyboard($keyboard)->send();
    }

    public function calcBySquareMeters(): void
    {
        $draft = $this->draftAtStep(OrderDraftStep::AwaitingCalcChoice);

        if ($draft === null) {
            return;
        }

        $draft->update(['step' => OrderDraftStep::AwaitingSquareMeters]);

        $this->chat
            ->html('Necha kvadrat metr? (masalan: 12.5)')
            ->keyboard($this->cancelKeyboard())
            ->send();
    }

    public function calcByDimensions(): void
    {
        $draft = $this->draftAtStep(OrderDraftStep::AwaitingCalcChoice);

        if ($draft === null) {
            return;
        }

        $draft->update(['step' => OrderDraftStep::AwaitingWidth]);

        $this->chat
            ->html('Eni necha metr? (masalan: 1.5)')
            ->keyboard($this->cancelKeyboard())
            ->send();
    }

    public function customerNew(): void
    {
        $draft = $this->draftAtStep(OrderDraftStep::AwaitingCustomerChoice);

        if ($draft === null) {
            return;
        }

        $draft->update(['step' => OrderDraftStep::AwaitingCustomerName]);

        $this->chat
            ->html('Mijozning ismini kiriting:')
            ->keyboard($this->cancelKeyboard())
            ->send();
    }

    public function customerExisting(): void
    {
        $draft = $this->draftAtStep(OrderDraftStep::AwaitingCustomerChoice);

        if ($draft === null) {
            return;
        }

        $draft->update(['step' => OrderDraftStep::AwaitingCustomerSelection]);

        $this->showCustomersPage(1);
    }

    public function customersPage(string $page): void
    {
        $draft = $this->draftAtStep(OrderDraftStep::AwaitingCustomerSelection);

        if ($draft === null) {
            return;
        }

        $this->showCustomersPage((int) $page);
    }

    public function selectCustomer(string $id): void
    {
        $draft = $this->draftAtStep(OrderDraftStep::AwaitingCustomerSelection);

        if ($draft === null) {
            return;
        }

        $customer = Customer::find((int) $id);

        if ($customer === null) {
            $this->chat->html("Mijoz topilmadi, qayta urinib ko'ring.")->send();

            return;
        }

        $draft->update([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'step' => OrderDraftStep::AwaitingConfirmation,
        ]);

        $this->sendSummary($draft);
    }

    public function customerNone(): void
    {
        $draft = $this->draftAtStep(OrderDraftStep::AwaitingCustomerChoice);

        if ($draft === null) {
            return;
        }

        $draft->update([
            'step' => OrderDraftStep::AwaitingConfirmation,
            'customer_id' => null,
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
            'customer_id' => $draft->customer_id,
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
        $squareMeters = $this->parsePositiveDecimal($value);

        if ($squareMeters === null) {
            $this->chat
                ->html("Noto'g'ri format. Iltimos, raqam kiriting (masalan: 12.5)")
                ->keyboard($this->cancelKeyboard())
                ->send();

            return;
        }

        $draft->update([
            'square_meters' => $squareMeters,
            'step' => OrderDraftStep::AwaitingCustomerChoice,
        ]);

        $this->askCustomerChoice();
    }

    private function handleWidthInput(OrderDraft $draft, string $value): void
    {
        $width = $this->parsePositiveDecimal($value);

        if ($width === null) {
            $this->chat
                ->html("Noto'g'ri format. Iltimos, raqam kiriting (masalan: 1.5)")
                ->keyboard($this->cancelKeyboard())
                ->send();

            return;
        }

        $draft->update([
            'width_meters' => $width,
            'step' => OrderDraftStep::AwaitingHeight,
        ]);

        $this->chat
            ->html("Bo'yi necha metr? (masalan: 2.0)")
            ->keyboard($this->cancelKeyboard())
            ->send();
    }

    private function handleHeightInput(OrderDraft $draft, string $value): void
    {
        $height = $this->parsePositiveDecimal($value);

        if ($height === null) {
            $this->chat
                ->html("Noto'g'ri format. Iltimos, raqam kiriting (masalan: 2.0)")
                ->keyboard($this->cancelKeyboard())
                ->send();

            return;
        }

        $squareMeters = round((float) $draft->width_meters * $height, 2);

        $draft->update([
            'height_meters' => $height,
            'square_meters' => $squareMeters,
            'step' => OrderDraftStep::AwaitingCustomerChoice,
        ]);

        $this->chat->html("Hisoblangan maydon: {$squareMeters} kv.m")->send();

        $this->askCustomerChoice();
    }

    private function askCustomerChoice(): void
    {
        $keyboard = Keyboard::make()
            ->row([Button::make('🆕 Yangi mijoz')->action('customerNew')])
            ->row([Button::make('📋 Mavjud mijozlardan tanlash')->action('customerExisting')])
            ->row([Button::make('➖ Mijozsiz davom etish')->action('customerNone')])
            ->row([$this->cancelButton()]);

        $this->chat->html("Mijoz ma'lumoti bormi?")->keyboard($keyboard)->send();
    }

    private function showCustomersPage(int $page): void
    {
        $page = max(1, $page);

        $customers = Customer::withMax('orders as last_order_at', 'created_at')
            ->orderByDesc('last_order_at')
            ->orderByDesc('id')
            ->paginate(self::CUSTOMERS_PER_PAGE, ['*'], 'page', $page);

        if ($customers->isEmpty()) {
            $this->chat
                ->html("Hozircha ro'yxatda mijoz yo'q.")
                ->keyboard($this->cancelKeyboard())
                ->send();

            return;
        }

        $keyboard = Keyboard::make();

        foreach ($customers as $index => $customer) {
            $number = ($customers->currentPage() - 1) * self::CUSTOMERS_PER_PAGE + $index + 1;

            $keyboard = $keyboard->row([
                Button::make("{$number}. {$customer->name} — {$customer->phone}")
                    ->action('selectCustomer')
                    ->param('id', $customer->id),
            ]);
        }

        $navButtons = [];

        if ($customers->currentPage() > 1) {
            $navButtons[] = Button::make('⬅️ Oldingisi')->action('customersPage')->param('page', $customers->currentPage() - 1);
        }

        if ($customers->hasMorePages()) {
            $navButtons[] = Button::make('➡️ Keyingisi')->action('customersPage')->param('page', $customers->currentPage() + 1);
        }

        if ($navButtons !== []) {
            $keyboard = $keyboard->row($navButtons);
        }

        $keyboard = $keyboard->row([$this->cancelButton()]);

        $this->chat->html('Mijozni tanlang:')->keyboard($keyboard)->send();
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

        $existingCustomer = Customer::where('phone', $value)->first();

        $customer = $existingCustomer ?? Customer::create([
            'name' => $draft->customer_name,
            'phone' => $value,
        ]);

        $draft->update([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'step' => OrderDraftStep::AwaitingConfirmation,
        ]);

        if ($existingCustomer !== null) {
            $this->chat->html("Bu mijoz allaqachon ro'yxatda, unga bog'landi.")->send();
        }

        $this->sendSummary($draft);
    }

    private function parsePositiveDecimal(string $value): ?float
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
