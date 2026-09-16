<?php

namespace App\Telegram\Handlers;

use App\Enums\WorkerStatus;
use App\Models\Worker;
use DefStudio\Telegraph\DTO\User as TelegramUser;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;

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
