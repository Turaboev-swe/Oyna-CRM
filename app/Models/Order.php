<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'worker_id',
        'customer_id',
        'customer_name',
        'customer_phone',
        'square_meters',
        'price_per_sqm_snapshot',
        'total_price',
        'status',
    ];

    protected $casts = [
        'square_meters' => 'decimal:2',
        'price_per_sqm_snapshot' => 'decimal:2',
        'total_price' => 'decimal:2',
        'status' => OrderStatus::class,
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
