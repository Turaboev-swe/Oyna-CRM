<?php

namespace App\Models;

use App\Enums\WorkerStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function orderDraft(): HasOne
    {
        return $this->hasOne(OrderDraft::class);
    }
}
