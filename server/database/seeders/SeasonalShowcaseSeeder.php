<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\Label;
use App\Models\Trip;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class SeasonalShowcaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Ogledne sezonske ponude dozvoljene su samo lokalno ili u testovima.');
        }

        DB::transaction(function (): void {
            $summer = Label::resolveName('category', 'Ljetovanje 2027', true);
            $advent = Label::resolveName('category', 'Advent 2026', true);
            $coast = Label::resolveName('region', 'Jadran');
            $amenities = collect(['Bazen', 'Besplatan parking', 'Privatna plaža'])
                ->map(fn (string $name): int => Label::resolveName('amenity', $name)->id);

            foreach ([
                ['ogledni-zadar-ljeto', 'Ljeto u Zadru', 'Zadar', '2027-07-10', '2027-07-17', [$summer->id, $coast->id]],
                ['ogledni-zagreb-advent', 'Advent u Zagrebu', 'Zagreb', '2026-12-12', '2026-12-13', [$advent->id]],
            ] as [$slug, $name, $destination, $start, $end, $labels]) {
                $trip = Trip::firstOrCreate(['slug' => $slug], [
                    'title' => $name.' · ogledna ponuda', 'destination' => $destination, 'transport' => 'bus', 'status' => 'published',
                ]);
                if ($trip->wasRecentlyCreated) {
                    $trip->labels()->attach($labels);
                    $trip->departures()->create(['starts_at' => $start, 'ends_at' => $end]);
                }
            }
            $hotel = Hotel::firstOrCreate(['slug' => 'ogledni-hotel-ljeto-zadar'], [
                'name' => 'Ljetni boravak u Zadru · ogledni hotel', 'destination' => 'Zadar', 'status' => 'published',
                'description' => 'Izmišljeni hotel za pregled sezonskih ponuda i sadržaja.',
            ]);
            if ($hotel->wasRecentlyCreated) {
                $hotel->labels()->attach([$summer->id, $coast->id, ...$amenities]);
                $package = $hotel->packages()->create(['name' => 'Tjedan uz more', 'price_basis' => 'person_package', 'nights' => 7]);
                $package->rates()->create(['starts_at' => '2027-07-10', 'ends_at' => '2027-07-17']);
            }
        });
    }
}
