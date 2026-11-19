<?php

namespace App\Models;

use Database\Factories\HotelRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['starts_at', 'ends_at', 'price', 'currency', 'availability'])]
class HotelRate extends Model
{
    /** @use HasFactory<HotelRateFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'price' => 'decimal:2',
        ];
    }

    public function hotelPackage(): BelongsTo
    {
        return $this->belongsTo(HotelPackage::class);
    }
}
