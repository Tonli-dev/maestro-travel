<?php

namespace Tests\Feature;

use App\Models\Departure;
use App\Models\Hotel;
use App\Models\HotelPackage;
use App\Models\HotelRate;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PublicCatalogTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2027-05-10 12:00:00', 'Europe/Sarajevo'));
    }

    public function test_empty_catalog_works_without_demo_or_inquiry_form(): void
    {
        $this->get('/')->assertOk()->assertSee('Trenutačno nema ponuda')->assertSee('Pronađite svoje')
            ->assertDontSee('maestro-travel-demo')->assertDontSee('id="inquiry"', false);
    }

    #[TestWith(['draft', 'trip'])]
    #[TestWith(['archived', 'trip'])]
    #[TestWith(['deleted', 'trip'])]
    #[TestWith(['draft', 'hotel'])]
    #[TestWith(['archived', 'hotel'])]
    #[TestWith(['deleted', 'hotel'])]
    public function test_hidden_offers_are_not_public_even_for_employees(string $status, string $type): void
    {
        $record = $type === 'trip'
            ? Trip::factory()->hasDepartures(1)->create(['title' => 'Skrivena ponuda', 'status' => $status === 'deleted' ? 'published' : $status])
            : Hotel::factory()->has(HotelPackage::factory()->hasRates(1), 'packages')->create(['name' => 'Skrivena ponuda', 'status' => $status === 'deleted' ? 'published' : $status]);
        if ($status === 'deleted') {
            $record->delete();
        }
        $url = route($type === 'trip' ? 'trips.show' : 'hotels.show', $record->slug);
        $this->get('/')->assertOk()->assertDontSee('Skrivena ponuda');
        $this->get($url)->assertNotFound();
        $this->actingAs(User::factory()->active()->create())->get($url)->assertNotFound();
    }

    public function test_catalog_sorts_and_filters_title_destination_type_and_transport(): void
    {
        Trip::factory()->hasDepartures(1)->create(['title' => 'Zadar', 'destination' => 'Hrvatska', 'transport' => 'bus', 'status' => 'published']);
        Hotel::factory()->has(HotelPackage::factory()->hasRates(1), 'packages')->create(['name' => 'Orašje hotel', 'status' => 'published']);
        $early = Trip::factory()->create(['title' => 'Ranije putovanje', 'transport' => 'plane', 'status' => 'published']);
        Departure::factory()->for($early)->create(['starts_at' => '2027-05-11', 'ends_at' => '2027-05-12']);
        $this->get('/')->assertOk()->assertViewHas('offers', fn ($offers): bool => $offers->pluck('title')->all() === ['Ranije putovanje', 'Zadar']);
        $this->get('/?q=orasJE&type=hotel')->assertOk()->assertViewHas('offers', fn ($offers): bool => $offers->pluck('title')->all() === ['Orašje hotel']);
        $this->get('/?q=hrvatska&transport=bus')->assertOk()->assertViewHas('offers', fn ($offers): bool => $offers->pluck('title')->all() === ['Zadar']);
        $this->get('/?type=hotel&transport=plane')->assertOk()->assertViewHas('offers', fn ($offers): bool => $offers->pluck('title')->all() === ['Orašje hotel']);
    }

    public function test_trip_minimum_excludes_past_and_sold_out_and_preserves_decimals(): void
    {
        $trip = Trip::factory()->create(['status' => 'published']);
        Departure::factory()->for($trip)->create(['starts_at' => '2027-05-09', 'ends_at' => '2027-05-14', 'price' => '1.00']);
        $sold = Departure::factory()->for($trip)->create(['price' => '2.00', 'availability' => 'sold_out']);
        $earliest = Departure::factory()->for($trip)->create(['starts_at' => '2027-05-10', 'ends_at' => '2027-05-12', 'price' => null]);
        Departure::factory()->for($trip)->create(['price' => '249.90']);
        $response = $this->get(route('trips.show', $trip->slug))->assertOk()
            ->assertSeeText('249,90 KM')->assertSee('Cijena na upit')->assertSee('Raspoloživost na upit')
            ->assertSee('Popunjeno')->assertDontSeeText('1,00 KM')->assertDontSeeText('2,00 KM');
        $this->assertSame($earliest->id, $response->viewData('selected')['id']);
        $this->assertSame('249.90', $response->viewData('offer')['minima']->sole()['price']);
        $this->get(route('trips.show', ['trip' => $trip->slug, 'departure' => $sold->id]))
            ->assertOk()->assertSee('Odabrani termin nije dostupan')->assertViewHas('selected', fn ($selected): bool => $selected['id'] === $earliest->id);
    }

    public function test_hotel_minima_keep_all_four_price_bases_separate(): void
    {
        $hotel = Hotel::factory()->create(['status' => 'published']);
        foreach (['person_package' => '249.90', 'room_package' => '399.90', 'person_night' => '49.90', 'room_night' => '89.90'] as $basis => $price) {
            $package = HotelPackage::factory()->for($hotel)->create(['price_basis' => $basis]);
            HotelRate::factory()->for($package)->create(['price' => $price]);
        }
        $response = $this->get(route('hotels.show', $hotel->slug))->assertOk()
            ->assertSee('po osobi / paket')->assertSee('po sobi / paket')->assertSee('po osobi / noć')->assertSee('po sobi / noć');
        $this->assertCount(4, $response->viewData('offer')['minima']);
        $this->get('/?type=hotel')->assertOk()->assertSeeText('249,90 KM')->assertSeeText('399,90 KM')->assertSeeText('49,90 KM')->assertSeeText('89,90 KM');
    }

    #[TestWith(['trip'])]
    #[TestWith(['hotel'])]
    public function test_no_current_options_hides_card_but_retains_published_detail(string $type): void
    {
        $record = $type === 'trip'
            ? Trip::factory()->create(['status' => 'published', 'title' => 'Istekla ponuda'])
            : Hotel::factory()->create(['status' => 'published', 'name' => 'Istekla ponuda']);
        if ($type === 'trip') {
            Departure::factory()->for($record)->create(['starts_at' => '2027-05-09', 'ends_at' => '2027-05-12']);
        } else {
            HotelRate::factory()->for(HotelPackage::factory()->for($record)->create())->create(['availability' => 'sold_out']);
        }
        $this->get('/?type='.$type)->assertOk()->assertDontSee('Istekla ponuda');
        $this->get(route($type === 'trip' ? 'trips.show' : 'hotels.show', $record->slug))
            ->assertOk()->assertSee('Trenutačno nema aktualnih termina')
            ->assertDontSeeText('249,90 KM')->assertDontSeeText('149,90 KM')->assertViewHas('selected', null);
    }

    public function test_trip_selection_rejects_foreign_removed_and_malformed_ids_and_updates_contact(): void
    {
        config(['contact.whatsapp_number' => '387600000000', 'contact.viber_number' => '387600000001']);
        $trip = Trip::factory()->hasDepartures(1)->create(['status' => 'published', 'title' => 'Istanbul']);
        $second = Departure::factory()->for($trip)->create(['starts_at' => '2027-07-01', 'ends_at' => '2027-07-04', 'price' => '599.95', 'departure_place' => 'Tuzla']);
        $foreign = Departure::factory()->create();
        $url = route('trips.show', $trip->slug);
        $response = $this->get($url.'?departure='.$second->id)->assertOk()->assertSeeText('599,95 KM')->assertSee('Tuzla')
            ->assertSee('Kopiraj podatke za Viber')->assertSee('name="departure"', false);
        $this->assertStringContainsString('01.07.2027.', rawurldecode($response->viewData('whatsapp')));
        $this->assertStringContainsString('599,95 KM po osobi', $response->viewData('message'));
        $second->delete();
        foreach (['departure='.$foreign->id, 'departure[]=1', 'departure=invalid', 'departure='.$second->id] as $query) {
            $this->get($url.'?'.$query)->assertOk()->assertSee('Odabrani termin nije dostupan')
                ->assertViewHas('selected', fn ($selected): bool => $selected['id'] === $trip->departures->sole()->id);
        }
    }

    public function test_hotel_selection_validates_package_and_rate_together(): void
    {
        config(['contact.whatsapp_number' => '387600000000']);
        $hotel = Hotel::factory()->create(['status' => 'published', 'name' => 'More']);
        $first = HotelPackage::factory()->for($hotel)->hasRates(1)->create(['name' => 'Doručak']);
        $second = HotelPackage::factory()->for($hotel)->hasRates(1)->create(['name' => 'Polupansion', 'price_basis' => 'room_package']);
        $foreign = HotelRate::factory()->create();
        $url = route('hotels.show', $hotel->slug);
        $response = $this->get($url.'?package='.$second->id.'&rate='.$second->rates->sole()->id)->assertOk();
        $this->assertSame($second->id, $response->viewData('selected')['package']->id);
        $this->assertStringContainsString('Paket: Polupansion.', $response->viewData('message'));
        $this->assertStringContainsString('po sobi / paket', rawurldecode($response->viewData('whatsapp')));
        foreach (['package='.$first->id.'&rate='.$second->rates->sole()->id, 'package='.$first->id.'&rate='.$foreign->id, 'rate='.$second->rates->sole()->id] as $query) {
            $invalid = $this->get($url.'?'.$query)->assertOk()->assertSee('Odabrani termin nije dostupan')
                ->assertViewHas('selected', null)->assertViewHas('invalidSelection', true);
            $this->assertStringNotContainsString('Termin:', $invalid->viewData('message'));
        }
    }

    #[TestWith(['trip'])]
    #[TestWith(['hotel'])]
    public function test_midnight_in_sarajevo_expires_yesterdays_options(string $type): void
    {
        $this->travelTo(Carbon::parse('2027-05-10 23:59:59', 'Europe/Sarajevo'));
        $record = $type === 'trip' ? Trip::factory()->create(['status' => 'published']) : Hotel::factory()->create(['status' => 'published']);
        $parent = $type === 'trip' ? $record : HotelPackage::factory()->for($record)->create();
        $factory = $type === 'trip' ? Departure::factory() : HotelRate::factory();
        $today = $factory->for($parent)->create(['starts_at' => '2027-05-10', 'ends_at' => '2027-05-13']);
        $tomorrow = $factory->for($parent)->create(['starts_at' => '2027-05-11', 'ends_at' => '2027-05-13']);
        $url = route($type === 'trip' ? 'trips.show' : 'hotels.show', $record->slug);
        if ($type === 'hotel') {
            $url .= '?package='.$parent->id.'&rate='.$today->id;
        }
        $this->get($url)->assertOk()->assertViewHas('selected', fn ($selected): bool => $selected['id'] === $today->id);
        $this->travelTo(Carbon::parse('2027-05-11 00:00:00', 'Europe/Sarajevo'));
        if ($type === 'hotel') {
            $this->get($url)->assertOk()->assertViewHas('selected', null)->assertViewHas('invalidSelection', true)
                ->assertViewHas('visibleOptions', fn ($options): bool => $options->sole()['id'] === $tomorrow->id);
        } else {
            $this->get($url)->assertOk()->assertViewHas('selected', fn ($selected): bool => $selected['id'] === $tomorrow->id);
        }
    }

    public function test_content_is_escaped_and_missing_channels_have_no_demo_fallback(): void
    {
        config(['contact.whatsapp_number' => null, 'contact.viber_number' => null]);
        $payload = '<script>alert("xss")</script>';
        $trip = Trip::factory()->hasDepartures(1)->create([
            'status' => 'published', 'title' => $payload, 'description' => $payload, 'destination' => $payload,
            'program' => $payload, 'included' => $payload, 'excluded' => $payload,
        ]);
        $this->get(route('trips.show', $trip->slug))->assertOk()->assertSee($payload)->assertDontSee($payload, false)
            ->assertDontSee('https://wa.me/', false)->assertDontSee('Kopiraj podatke za Viber')->assertSee('card-image-placeholder', false);
        $this->get('/')->assertOk()->assertDontSee($payload, false);
        $hotel = Hotel::factory()->create(['status' => 'published', 'name' => $payload, 'address' => $payload]);
        HotelPackage::factory()->for($hotel)->hasRates(1)->create(['name' => $payload, 'notes' => $payload, 'room_type' => $payload, 'board' => $payload]);
        $this->get(route('hotels.show', $hotel->slug))->assertOk()->assertSee($payload)->assertDontSee($payload, false);
    }

    public function test_only_three_current_trips_are_featured_independent_of_filters(): void
    {
        Trip::factory()->hasDepartures(1)->count(4)->create(['status' => 'published']);
        $this->get('/?type=hotel')->assertOk()->assertViewHas('featured', fn ($featured): bool => $featured->count() === 3)
            ->assertViewHas('offers', fn ($offers): bool => $offers->isEmpty());
    }

    public function test_unpriced_current_offer_is_listed_without_zero_price(): void
    {
        Trip::factory()->has(Departure::factory()->state(['price' => null]), 'departures')->create(['status' => 'published']);
        $this->get('/')->assertOk()->assertSee('Cijena na upit')->assertDontSeeText('0,00 KM');
    }

    #[TestWith([1])]
    #[TestWith([2])]
    #[TestWith([3])]
    public function test_featured_trips_have_a_public_detail_link_with_any_supported_count(int $count): void
    {
        $trips = Trip::factory()->hasDepartures(1)->count($count)->create(['status' => 'published']);
        $response = $this->get('/')->assertOk()
            ->assertViewHas('featured', fn ($featured): bool => $featured->count() === $count);

        foreach ($trips as $trip) {
            $response->assertSee(route('trips.show', $trip->slug));
        }
    }

    public function test_transport_choices_are_available_in_trip_mode_even_when_search_hides_their_trips(): void
    {
        Trip::factory()->hasDepartures(1)->create(['status' => 'published', 'transport' => 'own']);
        $response = $this->get('/?type=hotel&q=More')->assertOk()
            ->assertViewHas('offers', fn ($offers): bool => $offers->isEmpty())
            ->assertDontSee('id="catalog-transport"', false);

        $this->get('/?type=trip&q=More')->assertOk()->assertViewHas('availableTransports', ['own'])->assertSee('Vlastiti prijevoz');
        $this->get('/?transport=own')->assertOk()
            ->assertViewHas('offers', fn ($offers): bool => $offers->count() === 1);
    }

    public function test_floating_and_inline_whatsapp_use_the_same_selected_term(): void
    {
        config(['contact.whatsapp_number' => '387600000000']);
        $trip = Trip::factory()->hasDepartures(1)->create(['status' => 'published']);
        $departure = Departure::factory()->for($trip)->create(['price' => '599.95', 'starts_at' => '2027-07-01', 'ends_at' => '2027-07-04']);
        $response = $this->get(route('trips.show', ['trip' => $trip->slug, 'departure' => $departure->id]))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $contacts = $xpath->query('//a[@data-whatsapp-context or @id="whatsapp-floating"]');

        $this->assertCount(2, $contacts);
        foreach ($contacts as $contact) {
            $this->assertSame($response->viewData('whatsapp'), $contact->getAttribute('href'));
            $this->assertStringContainsString('599,95 KM', rawurldecode($contact->getAttribute('href')));
        }
    }

    public function test_compact_terms_keep_years_when_a_trip_crosses_new_year(): void
    {
        $trip = Trip::factory()->create(['status' => 'published']);
        Departure::factory()->for($trip)->create(['starts_at' => '2027-12-30', 'ends_at' => '2028-01-02']);
        Departure::factory()->for($trip)->create(['starts_at' => '2028-02-01', 'ends_at' => '2028-02-03']);

        $this->get(route('trips.show', $trip->slug))->assertOk()->assertSeeText('30.12.2027.–02.01.2028.')
            ->assertSeeText('2027–2028.')
            ->assertViewHas('selected', fn ($selected): bool => $selected['duration_label'] === '4 dana');
    }

    #[TestWith([0])]
    #[TestWith([1])]
    #[TestWith([3])]
    public function test_trip_has_one_inquiry_and_a_consistent_reading_order_for_any_departure_count(int $count): void
    {
        config(['contact.whatsapp_number' => '387600000000', 'contact.viber_number' => '387600000001']);
        $trip = Trip::factory()->hasDepartures($count)->create([
            'status' => 'published', 'description' => 'Opis putovanja.',
            'program' => 'Prvi dan|Program prvog dana.', 'included' => 'Prijevoz', 'excluded' => 'Osiguranje',
        ]);
        $response = $this->get(route('trips.show', $trip->slug))->assertOk()
            ->assertSeeInOrder(['class="trip-media"', 'id="trip-about-heading"', 'id="termini"', 'data-trip-inquiry', 'id="program"'], false)
            ->assertSeeText('Program prvog dana.')->assertSeeText('Prijevoz')->assertSeeText('Osiguranje');
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        $xpath = new \DOMXPath($document);

        $this->assertCount(1, $xpath->query('//*[@data-trip-inquiry]'));
        $this->assertCount(1, $xpath->query('//*[@id="contact-message"]'));
        $this->assertCount($count, $xpath->query('//*[@data-trip-options]//button[@role="radio"]'));
        $this->assertCount(0, $xpath->query('//*[@class="selected-term-summary" or @class="booking" or @data-hotel-options]'));
        if ($count > 0) {
            $this->assertCount(1, $xpath->query('//*[@data-trip-options]//button[@aria-checked="true"]'));
            $this->assertCount(1, $xpath->query('//*[@data-trip-inquiry]//a[@data-whatsapp-context]'));
        } else {
            $response->assertViewHas('selected', null)->assertSee('Trenutačno nema aktualnih termina');
        }
    }
}
