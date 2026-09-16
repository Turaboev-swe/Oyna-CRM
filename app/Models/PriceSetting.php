<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceSetting extends Model
{
    use HasFactory;

    const UPDATED_AT = 'updated_at';

    const CREATED_AT = null;

    protected $fillable = [
        'price_per_sqm',
        'updated_by',
    ];

    protected $casts = [
        'price_per_sqm' => 'decimal:2',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected static function booted(): void
    {
        static::updating(function (PriceSetting $priceSetting) {
            if ($priceSetting->isDirty('price_per_sqm')) {
                PriceHistory::create([
                    'price_per_sqm' => $priceSetting->getOriginal('price_per_sqm'),
                    'changed_by' => auth()->id() ?? $priceSetting->getOriginal('updated_by'),
                    'changed_at' => now(),
                ]);
            }
        });
    }
}
