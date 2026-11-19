<?php

namespace App\Support;

use App\Models\Departure;
use App\Models\Hotel;
use App\Models\HotelPackage;
use App\Models\HotelRate;
use App\Models\Trip;
use App\Models\TripProgram;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CatalogPresentation
{
    public const BASES = [
        'person_package' => 'po osobi / paket',
        'room_package' => 'po sobi / paket',
        'person_night' => 'po osobi / noć',
        'room_night' => 'po sobi / noć',
    ];

    public const AVAILABILITY = [
        'available' => 'Raspoloživo',
        'on_request' => 'Raspoloživost na upit',
        'sold_out' => 'Popunjeno',
    ];

    public const TRANSPORT = ['bus' => 'Autobusom', 'plane' => 'Avionom', 'own' => 'Vlastiti prijevoz'];

    public function future(Builder|Relation $query): void
    {
        $query->whereDate('starts_at', '>=', now('Europe/Sarajevo')->toDateString());
    }

    public function eligible(Builder $query): void
    {
        $this->future($query);
        $query->whereIn('availability', ['available', 'on_request']);
    }

    /** @return array<string, mixed> */
    public function offer(Trip|Hotel $record): array
    {
        $isHotel = $record instanceof Hotel;
        $record->loadMissing($isHotel ? ['packages.rates' => $this->future(...)] : ['departures' => $this->future(...)]);
        $options = $isHotel
            ? $record->packages->flatMap(fn (HotelPackage $package): Collection => $package->rates->map(
                fn (HotelRate $rate): array => $this->option($rate, $package->price_basis, $package),
            ))
            : $record->departures->map(fn (Departure $departure): array => $this->option($departure, 'person_package'));
        $options = $options->sort(fn (array $a, array $b): int => [$a['starts_at']->toDateString(), $a['id']]
            <=> [$b['starts_at']->toDateString(), $b['id']])->values();
        $selectable = $options->where('selectable', true)->values();
        $minima = $selectable->whereNotNull('price')->groupBy('basis')->map(
            fn (Collection $rows): array => $rows->sortBy(fn (array $row): int => (int) str_replace('.', '', $row['price']))->first(),
        )->values();
        $first = $selectable->first();

        return [
            'record' => $record, 'type' => $isHotel ? 'hotel' : 'trip',
            'title' => $isHotel ? $record->name : $record->title,
            'url' => route($isHotel ? 'hotels.show' : 'trips.show', $record->slug),
            'transport' => $isHotel ? null : (self::TRANSPORT[$record->transport] ?? null),
            'images' => collect($record->images ?? [])->map(fn (string $path): string => Storage::disk('public')->url($path))->all(),
            'options' => $options, 'selectable' => $selectable, 'minima' => $minima,
            'next_date' => $first ? $first['starts_at']->toDateString() : null,
            'duration_label' => $first['duration_label'] ?? null,
            'departure_place' => $first['departure_place'] ?? null,
            'date_label' => $selectable->count() > 1
                ? $selectable->count().' termina · od '.$first['starts_at']->format('d.m.Y.')
                : ($first ? $first['starts_at']->format('d.m.Y.').' – '.$first['ends_at']->format('d.m.Y.') : null),
        ];
    }

    /** @return array<string, mixed> */
    public function detail(Trip|Hotel $record, Request $request): array
    {
        $isHotel = $record instanceof Hotel;
        if (! $isHotel) {
            $record->loadMissing(['departures' => function (Relation $query): void {
                $this->future($query);
                $query->with('program');
            }]);
        }
        $offer = $this->offer($record);
        $requested = $request->query($isHotel ? 'rate' : 'departure');
        $package = $request->query('package');
        $selectionRequested = $request->hasAny($isHotel ? ['package', 'rate'] : ['departure']);
        $selected = $offer['selectable']->first(fn (array $option): bool => $this->matchesId($requested, $option['id'])
            && (! $isHotel || $this->matchesId($package, $option['package']->id)));
        $invalidSelection = $selectionRequested && $selected === null;
        $selected ??= $offer['selectable']->first();
        $itinerary = $isHotel ? null : $this->itinerary($record, $selected);
        $message = 'Pozdrav! Zanima me '.$offer['title'].'.';
        if ($selected !== null) {
            $message .= ' Termin: '.$selected['starts_at']->format('d.m.Y.').' – '.$selected['ends_at']->format('d.m.Y.');
            if ($isHotel) {
                $message .= ' Paket: '.$selected['package']->name.'.';
            } elseif (filled($selected['departure_place'])) {
                $message .= ' Polazište: '.$selected['departure_place'].'.';
            }
            if (($itinerary['kind'] ?? null) === 'structured') {
                $message .= ' Program: '.$itinerary['name'].'.';
            }
            $message .= ' '.$selected['price_label'].' '.$selected['basis_label'].'. '.$selected['availability_label'].'.';
        }
        $message .= ' Možete li mi poslati više informacija?';
        $contacts = app(ContactLinks::class);

        return compact('offer', 'selected', 'invalidSelection', 'message', 'itinerary') + [
            'preview' => false, 'whatsapp' => $contacts->whatsapp($message), 'viber' => $contacts->viberNumber(),
        ];
    }

    /**
     * The one itinerary that matches the already validated departure selection.
     *
     * - structured: the selected departure's own program (never another trip's)
     * - missing: the selected departure names a program that cannot be rendered safely
     * - legacy: the shared free-text program, for unassigned departures or when no departure is current
     * - contact: only per-departure programs exist and no departure is current
     * - null: nothing to show; the itinerary heading and link are hidden
     *
     * @param  array<string, mixed>|null  $selected
     * @return array{kind: string, name?: string, days?: list<array{title: string, description: string}>, day_label?: string, text?: string}|null
     */
    public function itinerary(Trip $trip, ?array $selected): ?array
    {
        $departure = $selected['model'] ?? null;
        if ($departure instanceof Departure && $departure->trip_program_id !== null) {
            $program = $departure->program;
            $days = $program !== null && $program->trip_id === $trip->id ? $program->renderableDays() : null;

            return $days === null ? ['kind' => 'missing'] : [
                'kind' => 'structured', 'name' => $program->name, 'days' => $days,
                'day_label' => TripProgram::dayLabel(count($days)),
            ];
        }
        if (filled($trip->program)) {
            return ['kind' => 'legacy', 'text' => $trip->program];
        }
        if ($selected === null && $trip->programs()->exists()) {
            return ['kind' => 'contact'];
        }

        return null;
    }

    public function normalize(string $text): string
    {
        return Str::ascii(mb_strtolower($text));
    }

    public static function money(?string $amount): string
    {
        if ($amount === null) {
            return 'Cijena na upit';
        }
        [$whole, $fraction] = explode('.', $amount.'.00');

        return number_format((int) $whole, 0, ',', '.').','.str_pad(substr($fraction, 0, 2), 2, '0').' KM';
    }

    private function matchesId(mixed $input, int $id): bool
    {
        return is_string($input) && ctype_digit($input) && $input === (string) $id;
    }

    /** @return array<string, mixed> */
    private function option(Departure|HotelRate $rate, string $basis, ?HotelPackage $package = null): array
    {
        $days = (int) $rate->starts_at->diffInDays($rate->ends_at) + 1;
        $sameMonth = $rate->starts_at->format('Y-m') === $rate->ends_at->format('Y-m');
        $sameYear = $rate->starts_at->year === $rate->ends_at->year;

        return [
            'id' => $rate->id, 'model' => $rate, 'package' => $package,
            'starts_at' => $rate->starts_at, 'ends_at' => $rate->ends_at,
            'price' => $rate->price, 'price_label' => self::money($rate->price),
            'basis' => $basis, 'basis_label' => $package ? (self::BASES[$basis] ?? $basis) : 'po osobi',
            'availability' => $rate->availability,
            'availability_label' => self::AVAILABILITY[$rate->availability] ?? 'Nedostupno',
            'selectable' => in_array($rate->availability, ['available', 'on_request'], true),
            'departure_place' => $rate instanceof Departure ? $rate->departure_place : null,
            'duration_label' => $days.' '.($days % 10 === 1 && $days % 100 !== 11 ? 'dan' : 'dana'),
            'range_label' => $rate->starts_at->format($sameMonth ? 'd.' : ($sameYear ? 'd.m.' : 'd.m.Y.')).'–'.$rate->ends_at->format($sameYear ? 'd.m.' : 'd.m.Y.'),
        ];
    }
}
