<?php

namespace App\Models;

use Database\Factories\DepartureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['starts_at', 'ends_at', 'departure_place', 'price', 'currency', 'availability', 'trip_program_id'])]
class Departure extends Model
{
    /** @use HasFactory<DepartureFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'price' => 'decimal:2',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(TripProgram::class, 'trip_program_id');
    }
}
