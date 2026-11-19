<?php

namespace App\Models;

use App\Support\CatalogSlug;
use Database\Factories\TripFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['title', 'slug', 'destination', 'transport', 'description', 'program', 'included', 'excluded', 'images', 'status', 'is_featured'])]
class Trip extends Model
{
    /** @use HasFactory<TripFactory> */
    use HasCatalogLabels, HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Trip $trip): void {
            if (blank($trip->slug)) {
                $trip->slug = CatalogSlug::generate($trip, $trip->title);
            }
        });
        static::saving(function (Trip $trip): void {
            if ($trip->status !== 'published') {
                $trip->is_featured = false;
            }
        });
        static::forceDeleting(function (Trip $trip): void {
            // Departure assignments restrict program deletion; release them so both cascades can run.
            $trip->departures()->update(['trip_program_id' => null]);
        });
    }

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'is_featured' => 'boolean',
        ];
    }

    public function departures(): HasMany
    {
        return $this->hasMany(Departure::class);
    }

    /** Only published, non-deleted trips can be starred for the homepage hero. */
    public function canBeFeatured(): bool
    {
        return $this->status === 'published' && ! $this->trashed();
    }

    public function programs(): HasMany
    {
        return $this->hasMany(TripProgram::class)->orderBy('name')->orderBy('id');
    }
}
