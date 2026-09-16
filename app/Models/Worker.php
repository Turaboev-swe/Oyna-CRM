<?php

namespace App\Models;

use App\Enums\WorkerStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Worker extends Model
{
    use HasFactory;

    protected $fillable = [
        'telegram_id',
        'ism',
        'telefon',
        'status',
    ];

    protected $casts = [
        'status' => WorkerStatus::class,
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
