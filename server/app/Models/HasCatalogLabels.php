<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

trait HasCatalogLabels
{
    protected static function bootHasCatalogLabels(): void
    {
        static::saving(function (Trip|Hotel $offer): void {
            $title = $offer instanceof Trip ? $offer->title : $offer->name;
            $offer->search_text = Str::ascii(mb_strtolower($title.' '.$offer->destination));
        });
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, $this instanceof Trip ? 'trip_labels' : 'hotel_labels');
    }

    public function categories(): BelongsToMany
    {
        return $this->labels()->where('labels.kind', 'category');
    }

    public function regions(): BelongsToMany
    {
        return $this->labels()->where('labels.kind', 'region');
    }
}
