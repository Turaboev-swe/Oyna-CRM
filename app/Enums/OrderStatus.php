<?php

namespace App\Enums;

enum OrderStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Yangi',
            self::InProgress => 'Jarayonda',
            self::Done => 'Bajarildi',
            self::Cancelled => 'Bekor qilindi',
        };
    }
}
