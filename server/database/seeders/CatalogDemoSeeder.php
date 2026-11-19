<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\Label;
use App\Models\Trip;
use Illuminate\Database\Seeder;
use LogicException;

class CatalogDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Ogledni sadrzaj dozvoljen je samo lokalno ili u testovima.');
        }

        $trip = Trip::firstOrCreate(['slug' => 'ogledno-putovanje'], [
            'title' => 'Ogledno putovanje — nije stvarna ponuda',
            'destination' => 'Ogledna destinacija', 'status' => 'draft',
        ]);
        $summer = Label::resolveName('category', 'Ljetovanje 2027', true);
        $coast = Label::resolveName('region', 'Jadran');
        Label::resolveName('category', 'Advent 2026', true);
        if ($trip->wasRecentlyCreated) {
            $trip->labels()->attach([$summer->id, $coast->id]);
        }
        foreach (['2027-06-01', '2027-07-01'] as $date) {
            if (! $trip->departures()->whereDate('starts_at', $date)->exists()) {
                $trip->departures()->create([
                    'starts_at' => $date, 'ends_at' => $date, 'price' => '100.00',
                    'currency' => 'BAM', 'availability' => 'on_request',
                ]);
            }
        }

        $hotel = Hotel::firstOrCreate(['slug' => 'ogledni-hotel'], [
            'name' => 'Ogledni hotel — nije stvarna ponuda',
            'destination' => 'Ogledna destinacija', 'status' => 'draft',
        ]);
        if ($hotel->wasRecentlyCreated) {
            $amenities = collect(['Bazen', 'Besplatan parking', 'Privatna plaža'])
                ->map(fn (string $name): int => Label::resolveName('amenity', $name)->id);
            $hotel->labels()->attach([$summer->id, $coast->id, ...$amenities]);
        }
        foreach (['Ogledni paket s doručkom', 'Ogledni paket s polupansionom'] as $name) {
            $package = $hotel->packages()->firstOrCreate(['name' => $name], ['nights' => 3, 'price_basis' => 'person_package']);
            if (! $package->rates()->whereDate('starts_at', '2027-06-01')->exists()) {
                $package->rates()->create([
                    'starts_at' => '2027-06-01', 'ends_at' => '2027-06-04', 'price' => '100.00',
                    'currency' => 'BAM', 'availability' => 'on_request',
                ]);
            }
        }
    }
}
