<?php

namespace App\Enums;

enum WorkerStatus: string
{
    case Active = 'active';
    case Pending = 'pending';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Faol',
            self::Pending => 'Kutilmoqda',
            self::Blocked => 'Bloklangan',
        };
    }
}
