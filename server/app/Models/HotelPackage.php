<?php

namespace App\Models;

use Database\Factories\HotelPackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'room_type', 'board', 'nights', 'price_basis', 'notes'])]
class HotelPackage extends Model
{
    /** @use HasFactory<HotelPackageFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'nights' => 'integer',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(HotelRate::class);
    }
}
