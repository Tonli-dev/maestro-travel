<?php

namespace App\Support;

use App\Models\Hotel;
use App\Models\InstagramPost;
use App\Models\Label;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class CatalogListing
{
    public function __construct(private CatalogPresentation $catalog) {}

    /** @param array{type: string, q?: string, transport?: string, category?: string, region?: string, amenities?: list<string>} $filters */
    public function results(array $filters): array
    {
        $hotel = $filters['type'] === 'hotel';
        $active = $this->current($hotel);
        $choices = Label::query()->whereHas($hotel ? 'hotels' : 'trips', fn (Builder $query) => $query->whereIn(
            $query->qualifyColumn('id'), (clone $active)->select('id'),
        ))->orderBy('name')->get();
        $query = clone $active;

        if (isset($filters['q'])) {
            $search = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $this->catalog->normalize($filters['q']));
            $query->whereRaw("search_text LIKE ? ESCAPE '!'", ['%'.$search.'%']);
        }
        if (isset($filters['transport'])) {
            $query->where('transport', $filters['transport']);
        }
        if (isset($filters['region'])) {
            $query->whereHas('regions', fn (Builder $q) => $q->where('slug', $filters['region']));
        }
        if (isset($filters['amenities'])) {
            $amenities = $filters['amenities'];
            $query->whereHas('amenities', fn (Builder $q) => $q->whereIn('slug', $amenities), '=', count($amenities));
        }

        $shortcuts = Label::query()->where('kind', 'category')->where('featured', true)
            ->whereHas($hotel ? 'hotels' : 'trips', fn (Builder $q) => $q->whereIn(
                $q->qualifyColumn('id'), (clone $query)->select('id'),
            ))->orderBy('name')->get();
        if (isset($filters['category'])) {
            $query->whereHas('categories', fn (Builder $q) => $q->where('slug', $filters['category']));
        }

        $offers = $this->ordered($query, $hotel)->paginate(12)->appends($filters)->fragment('ponude')
            ->through($this->catalog->offer(...));
        $featured = $this->featured();
        $availableTransports = $hotel ? [] : (clone $active)->whereNotNull('transport')->distinct()->pluck('transport')->all();

        $instagramPosts = InstagramPost::query()->ordered()->limit(InstagramPost::MAX_POSTS)->get();

        return compact('offers', 'featured', 'filters', 'shortcuts', 'availableTransports', 'instagramPosts') + [
            'choices' => $choices->groupBy('kind'),
            'chips' => $this->chips($filters),
            'preview' => false,
        ];
    }

    /**
     * Hero carousel: every starred current trip, by next date. Without one, the first three current trips.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function featured(): Collection
    {
        $starred = $this->ordered($this->current(false)->where('is_featured', true), false)->get();
        $trips = $starred->isNotEmpty() ? $starred : $this->ordered($this->current(false), false)->limit(3)->get();

        return $trips->map($this->catalog->offer(...));
    }

    private function current(bool $hotel): Builder
    {
        $query = $hotel ? Hotel::query() : Trip::query();

        return $query->where('status', 'published')->whereHas($hotel ? 'rates' : 'departures', $this->catalog->eligible(...));
    }

    private function ordered(Builder $query, bool $hotel): Builder
    {
        $dates = $hotel ? 'rates' : 'departures';

        return $query->withMin([$dates.' as next_date' => $this->catalog->eligible(...)], 'starts_at')
            ->with([$hotel ? 'packages.rates' : 'departures' => $this->catalog->future(...)])
            ->orderBy('next_date')->orderBy($hotel ? 'name' : 'title')->orderBy('id');
    }

    private function chips(array $filters): array
    {
        $chips = [];
        $selectedSlugs = array_merge(Arr::only($filters, ['category', 'region']), $filters['amenities'] ?? []);
        $labels = $selectedSlugs === [] ? collect() : Label::whereIn('slug', $selectedSlugs)->get();
        foreach (Arr::except($filters, ['type']) as $field => $value) {
            foreach (Arr::wrap($value) as $item) {
                $remaining = Arr::except($filters, [$field]);
                if ($field === 'amenities') {
                    $others = array_values(array_diff($value, [$item]));
                    if ($others !== []) {
                        $remaining[$field] = $others;
                    }
                }
                $kind = $field === 'amenities' ? 'amenity' : $field;
                $name = match ($field) {
                    'q' => $item,
                    'transport' => CatalogPresentation::TRANSPORT[$item],
                    default => $labels->where('kind', $kind)->firstWhere('slug', $item)?->name ?? $item,
                };
                $chips[] = ['field' => $field, 'value' => $item, 'name' => $name, 'remaining' => $remaining];
            }
        }

        return $chips;
    }
}
