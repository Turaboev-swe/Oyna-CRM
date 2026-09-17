<?php

namespace App\Models;

use App\Enums\OrderDraftStep;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'worker_id',
        'step',
        'square_meters',
        'width_meters',
        'height_meters',
        'customer_id',
        'customer_name',
        'customer_phone',
    ];

    protected $casts = [
        'step' => OrderDraftStep::class,
        'square_meters' => 'decimal:2',
        'width_meters' => 'decimal:2',
        'height_meters' => 'decimal:2',
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
