<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\Trip;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;

class CatalogShowcaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new LogicException('Ogledni katalog dozvoljen je samo lokalno.');
        }

        foreach (['istanbul.jpg', 'cinque-terre.jpg', 'andalusia.jpg'] as $image) {
            Storage::disk('public')->put('showcase/'.$image, file_get_contents(base_path('../assets/'.$image)));
        }

        DB::transaction(function (): void {
            foreach ([
                ['istanbul', 'Istanbul — grad na dva kontinenta', 'Istanbul, Turska', 'plane', 'istanbul.jpg', 'published', 69990],
                ['toskana', 'Toskana i Cinque Terre', 'Toskana, Italija', 'bus', 'cinque-terre.jpg', 'published', 47990],
                ['andaluzija', 'Andaluzija — sunce i povijest', 'Malaga, Španjolska', 'plane', 'andalusia.jpg', 'published', 129950],
                ['rim', 'Rim — proljetno putovanje', 'Rim, Italija', 'bus', 'cinque-terre.jpg', 'draft', 54900],
                ['istanbul-arhiva', 'Istanbul — prošlo ljeto', 'Istanbul, Turska', 'plane', 'istanbul.jpg', 'archived', 64900],
                ['andaluzija-arhiva', 'Andaluzija — prošla sezona', 'Malaga, Španjolska', 'plane', 'andalusia.jpg', 'archived', 119900],
            ] as [$slug, $title, $destination, $transport, $image, $status, $cents]) {
                $trip = Trip::firstOrCreate(['slug' => 'ogledni-katalog-'.$slug], [
                    'title' => $title.' · ogledna ponuda', 'destination' => $destination,
                    'transport' => $transport, 'status' => $status,
                    'images' => ['showcase/'.$image],
                    'description' => 'Ogledni sadržaj za lokalno testiranje — nije stvarna ponuda. Upoznajte destinaciju kroz organizirano putovanje i slobodno vrijeme. Fotografije su ilustrativne.',
                    'program' => "Prvi dan: Polazak i dolazak na destinaciju. Smještaj i slobodna večer.\nDrugi dan: Razgled povijesne jezgre uz pratnju vodiča.\nTreći dan: Slobodno vrijeme ili fakultativni izlet.\nČetvrti dan: Doručak, odjava i povratak.",
                    'included' => "Prijevoz prema programu\nTri noćenja s doručkom\nPratnja vodiča i organizacija putovanja",
                    'excluded' => "Putno zdravstveno osiguranje\nBoravišna pristojba\nFakultativni izleti i osobni troškovi\nDoplata za jednokrevetnu sobu na upit",
                ]);
                foreach ($this->terms($status === 'archived', $cents) as $term) {
                    $trip->departures()->firstOrCreate(['starts_at' => $term['starts_at']], $term + ['departure_place' => $transport === 'plane' ? 'Zračna luka Tuzla' : 'Orašje / Tuzla / Sarajevo']);
                }
            }

            foreach ([
                ['rivijera', 'Hotel Rivijera', 'Makarska, Hrvatska', 'published', 'cinque-terre.jpg', 24990],
                ['bosphorus', 'Hotel Bospor', 'Istanbul, Turska', 'published', 'istanbul.jpg', 31990],
                ['sierra', 'Hotel Sierra', 'Andaluzija, Španjolska', 'draft', 'andalusia.jpg', 28990],
                ['jadran-arhiva', 'Hotel Jadran — prošla sezona', 'Jadranska obala, Hrvatska', 'archived', 'cinque-terre.jpg', 19990],
                ['sunce-arhiva', 'Hotel Sunce — prošla sezona', 'Costa del Sol, Španjolska', 'archived', 'andalusia.jpg', 22990],
            ] as [$slug, $name, $destination, $status, $image, $cents]) {
                $hotel = Hotel::firstOrCreate(['slug' => 'ogledni-katalog-hotel-'.$slug], [
                    'name' => $name.' · ogledni hotel', 'destination' => $destination, 'stars' => 4,
                    'description' => 'Izmišljeni hotel za lokalni pregled izgleda i cijena — nije stvarna ponuda. Fotografija prikazuje destinaciju i nije fotografija hotela. Usporedite pakete, uslugu prehrane i termine boravka.',
                    'images' => ['showcase/'.$image], 'status' => $status,
                ]);
                foreach ([
                    ['Boravak s doručkom', 'person_package', 'Doručak', 3, 0],
                    ['Obiteljski paket s polupansionom', 'room_package', 'Polupansion', 3, 18000],
                    ['Noćenje s doručkom', 'person_night', 'Doručak', 1, -15000],
                ] as [$packageName, $basis, $board, $nights, $extra]) {
                    $package = $hotel->packages()->firstOrCreate(['name' => $packageName], [
                        'room_type' => 'Standardna dvokrevetna soba', 'board' => $board,
                        'nights' => $nights, 'price_basis' => $basis,
                        'notes' => 'Ogledni uvjeti. Boravišna pristojba i parking plaćaju se zasebno. Dječje cijene i dodatni ležaj na upit. Datumi označavaju fiksni boravak.',
                    ]);
                    foreach ($this->terms($status === 'archived', $cents + $extra, $nights) as $term) {
                        $package->rates()->firstOrCreate(['starts_at' => $term['starts_at']], $term);
                    }
                }
            }
        });
        $this->call(SeasonalShowcaseSeeder::class);
    }

    /** @return array<int, array<string, mixed>> */
    private function terms(bool $archived, int $cents, int $nights = 3): array
    {
        $anchor = now('Europe/Sarajevo')->startOfMonth();
        $terms = [];
        foreach ([-2, -1, 1, 2, 3, 4] as $index => $offset) {
            if ($archived && $offset > 0) {
                continue;
            }
            $start = $anchor->copy()->addMonths($offset)->addDays(14);
            $price = $index === 4 ? null : sprintf('%d.%02d', intdiv($cents + $index * 2500, 100), ($cents + $index * 2500) % 100);
            $terms[] = [
                'starts_at' => $start->toDateString(), 'ends_at' => $start->copy()->addDays($nights)->toDateString(),
                'price' => $price, 'currency' => 'BAM',
                'availability' => $index === 3 ? 'sold_out' : ($index === 4 ? 'on_request' : 'available'),
            ];
        }

        return $terms;
    }
}
