<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceHistory extends Model
{
    use HasFactory;

    protected $table = 'price_history';

    const CREATED_AT = null;

    const UPDATED_AT = null;

    protected $fillable = [
        'price_per_sqm',
        'changed_by',
        'changed_at',
    ];

    protected $casts = [
        'price_per_sqm' => 'decimal:2',
        'changed_at' => 'datetime',
    ];

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
