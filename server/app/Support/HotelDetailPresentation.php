<?php

namespace App\Support;

use App\Models\Hotel;
use App\Models\HotelPackage;
use Illuminate\Http\Request;

class HotelDetailPresentation
{
    public function __construct(private CatalogPresentation $catalog) {}

    /** @return array<string, mixed> */
    public function detail(Hotel $hotel, Request $request): array
    {
        $offer = $this->catalog->offer($hotel);
        $packages = $hotel->packages->map(function (HotelPackage $package) use ($offer): array {
            $options = $offer['options']->filter(fn (array $option): bool => $option['package']->id === $package->id)->values();
            $selectable = $options->where('selectable', true)->values();

            return [
                'record' => $package, 'options' => $options, 'selectable' => $selectable,
                'basis_label' => CatalogPresentation::BASES[$package->price_basis] ?? $package->price_basis,
                'minimum' => $selectable->whereNotNull('price')->sortBy(fn (array $row): int => (int) str_replace('.', '', $row['price']))->first(),
            ];
        })->values();
        $requestedPackage = $packages->first(fn (array $package): bool => $this->matches($request->query('package'), $package['record']->id));
        $activePackage = $requestedPackage ?? $packages->first(fn (array $package): bool => $package['selectable']->isNotEmpty()) ?? $packages->first();
        $options = $activePackage['options'] ?? collect();
        $selected = $requestedPackage ? $requestedPackage['selectable']->first(fn (array $option): bool => $this->matches($request->query('rate'), $option['id'])) : null;
        $invalidSelection = ($request->has('package') && $requestedPackage === null) || ($request->has('rate') && $selected === null);
        $hotelNotice = null;
        if ($invalidSelection) {
            $hotelNotice = $requestedPackage === null && $request->has('package')
                ? 'Odabrani paket nije dostupan. Odaberite paket i termin boravka.'
                : 'Odabrani termin nije dostupan ili ne pripada ovom paketu. Odaberite drugi termin.';
        }

        $months = $options->mapWithKeys(fn (array $option): array => [$option['starts_at']->format('Y-m') => $option['starts_at']->locale('hr')->translatedFormat('F Y.')])->sortKeys();
        $monthInput = $request->query('month');
        $month = is_string($monthInput) && $months->has($monthInput) ? $monthInput : null;
        if ($selected && $month !== null && $selected['starts_at']->format('Y-m') !== $month) {
            $selected = null;
            $invalidSelection = true;
            $hotelNotice = 'Odabrani termin nije u ovom mjesecu. Odaberite drugi termin.';
        }
        $filtered = $options->filter(fn (array $option): bool => $month === null || $option['starts_at']->format('Y-m') === $month)->values();
        $available = $filtered->where('selectable', true)->values();
        $soldOut = $filtered->where('availability', 'sold_out')->values();
        $showSoldOut = $request->query('sold_out') === '1';
        $visibleOptions = $available;
        $hotelQuery = array_filter([
            'package' => $activePackage['record']->id ?? null, 'rate' => $selected['id'] ?? null,
            'month' => $month ?? 'all', 'sold_out' => $showSoldOut ? '1' : null,
        ], fn (mixed $value): bool => $value !== null);
        $packages = $packages->map(function (array $package) use ($selected, $hotelQuery): array {
            $matching = $selected ? $package['selectable']->first(fn (array $option): bool => $option['starts_at']->equalTo($selected['starts_at']) && $option['ends_at']->equalTo($selected['ends_at'])) : null;
            $package['query'] = array_filter(array_replace($hotelQuery, [
                'package' => $package['record']->id, 'rate' => $matching['id'] ?? ($selected['id'] ?? null),
            ]), fn (mixed $value): bool => $value !== null);

            return $package;
        });

        $message = 'Pozdrav! Zanima me '.$offer['title'].'.';
        if ($activePackage !== null) {
            $message .= ' Paket: '.$activePackage['record']->name.'.';
        }
        if ($selected !== null) {
            $message .= ' Termin: '.$selected['starts_at']->format('d.m.Y.').' – '.$selected['ends_at']->format('d.m.Y.');
            $message .= ' '.$selected['price_label'].' '.$selected['basis_label'].'. '.$selected['availability_label'].'.';
        }
        $message .= ' Možete li mi poslati više informacija?';
        $contacts = app(ContactLinks::class);
        $selectionAnnouncement = $hotelNotice ?? ($selected
            ? 'Odabran termin '.$selected['starts_at']->format('d.m.Y.').' – '.$selected['ends_at']->format('d.m.Y.').' '.$selected['price_label'].' '.$selected['basis_label'].'. '.$selected['availability_label'].'.'
            : 'Prikazani su termini za paket '.($activePackage['record']->name ?? $offer['title']).'. Odaberite termin za upit.');
        $years = $options->flatMap(fn (array $option): array => [$option['starts_at']->year, $option['ends_at']->year]);

        return compact('offer', 'selected', 'invalidSelection', 'message', 'packages', 'activePackage', 'months', 'month', 'soldOut', 'showSoldOut', 'visibleOptions', 'hotelQuery', 'hotelNotice', 'selectionAnnouncement') + [
            'preview' => false, 'whatsapp' => $contacts->whatsapp($message), 'viber' => $contacts->viberNumber(),
            'availableCount' => $available->count(),
            'showMonthFilter' => $months->isNotEmpty(),
            'yearLabel' => $years->isEmpty() ? null : ($years->min() === $years->max() ? $years->min().'.' : $years->min().'–'.$years->max().'.'),
        ];
    }

    private function matches(mixed $input, int $id): bool
    {
        return is_string($input) && ctype_digit($input) && $input === (string) $id;
    }
}
