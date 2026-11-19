<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

#[Fillable(['name', 'kind', 'featured'])]
class Label extends Model
{
    use HasFactory;

    public const GROUPS = ['category' => 'Kategorije', 'region' => 'Regije', 'amenity' => 'Sadržaji hotela'];

    protected function casts(): array
    {
        return ['featured' => 'boolean'];
    }

    public static function nameKey(string $name): string
    {
        return Str::lower(Str::squish($name));
    }

    protected static function booted(): void
    {
        static::saving(function (Label $label): void {
            $label->name = Str::squish($label->name);
            $label->name_key = self::nameKey($label->name);
            $label->featured = $label->kind === 'category' && $label->featured;
            if (! $label->exists) {
                $base = Str::limit(Str::slug($label->name), 220, '') ?: 'oznaka';
                $label->slug = $base;
                $suffix = 2;
                while (self::query()->where('kind', $label->kind)->where('slug', $label->slug)->exists()) {
                    $label->slug = $base.'-'.$suffix++;
                }
            }
        });
    }

    public static function resolveName(string $kind, string $name, bool $featured = false): self
    {
        $identity = ['kind' => $kind, 'name_key' => self::nameKey($name)];
        // A concurrent insert can also claim the slug for a different spelling.
        try {
            return self::query()->firstOrCreate($identity, ['name' => $name, 'featured' => $featured]);
        } catch (UniqueConstraintViolationException) {
            return self::query()->firstOrCreate($identity, ['name' => $name, 'featured' => $featured]);
        }
    }

    public function trips(): BelongsToMany
    {
        return $this->belongsToMany(Trip::class, 'trip_labels');
    }

    public function hotels(): BelongsToMany
    {
        return $this->belongsToMany(Hotel::class, 'hotel_labels');
    }
}
