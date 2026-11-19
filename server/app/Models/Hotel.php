<?php

namespace App\Models;

use App\Support\CatalogSlug;
use Database\Factories\HotelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'destination', 'address', 'stars', 'description', 'images', 'status'])]
class Hotel extends Model
{
    /** @use HasFactory<HotelFactory> */
    use HasCatalogLabels, HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Hotel $hotel): void {
            if (blank($hotel->slug)) {
                $hotel->slug = CatalogSlug::generate($hotel, $hotel->name);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'stars' => 'integer',
        ];
    }

    public function amenities(): BelongsToMany
    {
        return $this->labels()->where('labels.kind', 'amenity');
    }

    public function rates(): HasManyThrough
    {
        return $this->hasManyThrough(HotelRate::class, HotelPackage::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(HotelPackage::class);
    }
}
