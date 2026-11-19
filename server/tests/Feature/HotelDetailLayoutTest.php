<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\HotelPackage;
use App\Models\HotelRate;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HotelDetailLayoutTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2027-05-10 12:00:00', 'Europe/Sarajevo'));
        config(['contact.whatsapp_number' => '387600000000', 'contact.viber_number' => '387600000001']);
    }

    public function test_initial_hotel_groups_packages_and_does_not_invent_a_selected_stay(): void
    {
        $hotel = Hotel::factory()->create(['status' => 'published']);
        $first = HotelPackage::factory()->for($hotel)->hasRates(2)->create(['name' => 'Doručak']);
        HotelPackage::factory()->for($hotel)->hasRates(3)->create(['name' => 'Polupansion', 'price_basis' => 'room_package']);

        $response = $this->get(route('hotels.show', $hotel->slug))->assertOk()->assertViewIs('catalog.hotel')
            ->assertViewHas('selected', null)->assertViewHas('invalidSelection', false)
            ->assertViewHas('packages', fn ($packages): bool => $packages->count() === 2)
            ->assertViewHas('activePackage', fn ($package): bool => $package['record']->id === $first->id)
            ->assertViewHas('visibleOptions', fn ($options): bool => $options->count() === 2);
        $this->assertStringNotContainsString('Termin:', $response->viewData('message'));
        $xpath = $this->xpath($response->getContent());
        $this->assertCount(1, $xpath->query('//*[@data-hotel-inquiry]'));
        $this->assertCount(1, $xpath->query('//*[@id="contact-message"]'));
        $this->assertCount(0, $xpath->query('//a[@data-whatsapp-context]'));
        $this->assertCount(2, $xpath->query('//*[@data-hotel-dates]//button[@role="radio"]'));
    }

    public function test_package_only_is_valid_and_shows_only_its_own_rates(): void
    {
        $hotel = Hotel::factory()->create(['status' => 'published']);
        HotelPackage::factory()->for($hotel)->hasRates(2)->create();
        $second = HotelPackage::factory()->for($hotel)->hasRates(3)->create(['price_basis' => 'room_night']);
        $response = $this->get(route('hotels.show', ['hotel' => $hotel->slug, 'package' => $second->id]))->assertOk()
            ->assertViewHas('selected', null)->assertViewHas('invalidSelection', false)
            ->assertViewHas('activePackage', fn ($package): bool => $package['record']->id === $second->id)
            ->assertViewHas('visibleOptions', fn ($options): bool => $options->count() === 3 && $options->every(fn ($option): bool => $option['package']->id === $second->id));
        $this->assertStringContainsString('Paket: '.$second->name, $response->viewData('message'));
        $this->assertStringNotContainsString('Termin:', $response->viewData('message'));
    }

    public function test_selected_rate_updates_the_only_inquiry_and_all_contact_links(): void
    {
        $hotel = Hotel::factory()->create(['status' => 'published']);
        $package = HotelPackage::factory()->for($hotel)->create(['name' => 'Obiteljski paket', 'price_basis' => 'room_package']);
        $rate = HotelRate::factory()->for($package)->create(['price' => '599.95', 'availability' => 'on_request']);
        $response = $this->get(route('hotels.show', ['hotel' => $hotel->slug, 'package' => $package->id, 'rate' => $rate->id]))->assertOk()
            ->assertSeeText('599,95 KM')->assertSeeText('po sobi / paket')->assertSeeText('Raspoloživost na upit');
        $this->assertSame($rate->id, $response->viewData('selected')['id']);
        $this->assertStringContainsString('599,95 KM po sobi / paket', $response->viewData('message'));
        $xpath = $this->xpath($response->getContent());
        $links = $xpath->query('//a[@data-whatsapp-context or @id="whatsapp-floating"]');
        $this->assertCount(2, $links);
        foreach ($links as $link) {
            $this->assertSame($response->viewData('whatsapp'), $link->getAttribute('href'));
        }
        $this->assertCount(1, $xpath->query('//*[@data-hotel-inquiry]'));
    }

    public function test_package_change_preserves_an_equivalent_stay_and_clears_an_unavailable_one(): void
    {
        $hotel = Hotel::factory()->create(['status' => 'published']);
        $first = HotelPackage::factory()->for($hotel)->create();
        $second = HotelPackage::factory()->for($hotel)->create();
        $third = HotelPackage::factory()->for($hotel)->create();
        $original = HotelRate::factory()->for($first)->create();
        $equivalent = HotelRate::factory()->for($second)->create();
        HotelRate::factory()->for($third)->create(['availability' => 'sold_out']);
        $url = route('hotels.show', $hotel->slug);
        $response = $this->get($url.'?package='.$first->id.'&rate='.$original->id)->assertOk();
        $query = $response->viewData('packages')->firstWhere('record.id', $second->id)['query'];
        $this->assertSame($equivalent->id, $query['rate']);
        $this->get($url.'?'.http_build_query($query))->assertOk()
            ->assertViewHas('selected', fn ($selected): bool => $selected['id'] === $equivalent->id);
        $unavailable = $response->viewData('packages')->firstWhere('record.id', $third->id)['query'];
        $cleared = $this->get($url.'?'.http_build_query($unavailable))->assertOk()->assertSee('Odabrani termin nije dostupan')
            ->assertViewHas('selected', null)->assertViewHas('invalidSelection', true)
            ->assertViewHas('activePackage', fn ($package): bool => $package['record']->id === $third->id);
        $this->assertStringNotContainsString('Termin:', $cleared->viewData('message'));
    }

    public function test_month_and_sold_out_toggle_are_scoped_to_package_and_year(): void
    {
        $hotel = Hotel::factory()->create(['status' => 'published']);
        $package = HotelPackage::factory()->for($hotel)->create();
        $june = HotelRate::factory()->for($package)->create(['starts_at' => '2027-06-10', 'ends_at' => '2027-06-13']);
        $sold = HotelRate::factory()->for($package)->create(['starts_at' => '2027-06-20', 'ends_at' => '2027-06-23', 'availability' => 'sold_out']);
        HotelRate::factory()->for($package)->create(['starts_at' => '2028-06-10', 'ends_at' => '2028-06-13', 'availability' => 'sold_out']);
        HotelRate::factory()->for(HotelPackage::factory()->for($hotel)->create())->create(['availability' => 'sold_out']);
        $url = route('hotels.show', ['hotel' => $hotel->slug, 'package' => $package->id, 'month' => '2027-06']);
        $hidden = $this->get($url)->assertOk()->assertViewHas('soldOut', fn ($options): bool => $options->count() === 1)
            ->assertViewHas('visibleOptions', fn ($options): bool => $options->sole()['id'] === $june->id)->assertViewHas('showSoldOut', false);
        $this->assertCount(0, $this->xpath($hidden->getContent())->query('//button[@disabled and @role="radio"]'));
        $shown = $this->get($url.'&sold_out=1')->assertOk()->assertViewHas('showSoldOut', true)->assertSee('Popunjeno');
        $this->assertCount(1, $this->xpath($shown->getContent())->query('//button[@disabled and @data-term-id="hotel-rate-'.$sold->id.'"]'));
    }

    public function test_all_months_are_shown_by_default_and_dates_can_be_filtered(): void
    {
        $hotel = Hotel::factory()->create(['status' => 'published']);
        $package = HotelPackage::factory()->for($hotel)->create();
        for ($index = 0; $index < 8; $index++) {
            $start = Carbon::parse('2027-06-01')->addDays($index * 2);
            HotelRate::factory()->for($package)->create(['starts_at' => $start->toDateString(), 'ends_at' => $start->copy()->addDays(3)->toDateString()]);
        }
        $later = HotelRate::factory()->for($package)->create(['starts_at' => '2027-07-01', 'ends_at' => '2027-07-04']);
        $url = route('hotels.show', ['hotel' => $hotel->slug, 'package' => $package->id]);
        $this->get($url)->assertOk()->assertViewHas('month', null)
            ->assertViewHas('visibleOptions', fn ($options): bool => $options->count() === 9)
            ->assertSee('9 raspoloživih termina')->assertDontSee('Prikaži još termina');
        $this->get($url.'&month=all')->assertOk()->assertViewHas('month', null)
            ->assertViewHas('visibleOptions', fn ($options): bool => $options->count() === 9)
            ->assertSee('Svi mjeseci');
        $this->get($url.'&month=all&rate='.$later->id)->assertOk()->assertViewHas('month', null)
            ->assertViewHas('selected', fn ($selected): bool => $selected['id'] === $later->id);
        $this->get($url.'&rate='.$later->id.'&month=2027-07')->assertOk()->assertViewHas('month', '2027-07')
            ->assertViewHas('selected', fn ($selected): bool => $selected['id'] === $later->id)
            ->assertViewHas('visibleOptions', fn ($options): bool => $options->count() === 1);
    }

    public function test_changing_month_clears_a_stay_outside_the_filter_with_an_explanation(): void
    {
        $hotel = Hotel::factory()->create(['status' => 'published']);
        $package = HotelPackage::factory()->for($hotel)->create();
        $june = HotelRate::factory()->for($package)->create();
        HotelRate::factory()->for($package)->create(['starts_at' => '2027-07-01', 'ends_at' => '2027-07-04']);
        $response = $this->get(route('hotels.show', ['hotel' => $hotel->slug, 'package' => $package->id, 'rate' => $june->id, 'month' => '2027-07']))
            ->assertOk()->assertSee('Odabrani termin nije u ovom mjesecu')->assertViewHas('selected', null)->assertViewHas('invalidSelection', true);
        $this->assertStringNotContainsString('Termin:', $response->viewData('message'));
    }

    public function test_malformed_selection_and_filter_parameters_do_not_select_a_stay_or_break_the_page(): void
    {
        $hotel = Hotel::factory()->create(['status' => 'published']);
        $package = HotelPackage::factory()->for($hotel)->hasRates(1)->create();
        $url = route('hotels.show', $hotel->slug);
        foreach (['package[]=1', 'package=invalid', 'package='.$package->id.'&rate[]=1', 'package='.$package->id.'&rate=invalid', 'package=999999&rate=999999'] as $query) {
            $response = $this->get($url.'?'.$query)->assertOk()->assertViewHas('selected', null)->assertViewHas('invalidSelection', true);
            $this->assertStringNotContainsString('Termin:', $response->viewData('message'));
        }
        $this->get($url.'?package='.$package->id.'&month[]=2027-06&limit[]=4&sold_out[]=1')->assertOk()
            ->assertViewHas('month', null)->assertViewHas('showSoldOut', false)
            ->assertViewHas('selected', null)->assertViewHas('invalidSelection', false);
    }

    public function test_on_request_and_empty_packages_have_honest_inquiry_states(): void
    {
        $hotel = Hotel::factory()->create(['status' => 'published']);
        $package = HotelPackage::factory()->for($hotel)->create();
        $rate = HotelRate::factory()->for($package)->create(['price' => null, 'availability' => 'on_request']);
        $url = route('hotels.show', ['hotel' => $hotel->slug, 'package' => $package->id, 'rate' => $rate->id]);
        $this->get($url)->assertOk()->assertSee('Cijena na upit')->assertDontSeeText('0,00 KM')
            ->assertViewHas('selected', fn ($selected): bool => $selected['id'] === $rate->id);
        $rate->update(['availability' => 'sold_out']);
        $this->get($url)->assertOk()->assertViewHas('selected', null)->assertSee('Trenutačno nema aktualnih termina')
            ->assertSee('Upit preko WhatsAppa')->assertDontSeeText('0,00 KM');
    }

    public function test_preview_uses_the_same_hotel_layout_and_selection_with_private_headers(): void
    {
        $hotel = Hotel::factory()->create(['status' => 'published']);
        $package = HotelPackage::factory()->for($hotel)->hasRates(1)->create();
        $query = ['package' => $package->id, 'rate' => $package->rates->sole()->id];
        $public = $this->get(route('hotels.show', ['hotel' => $hotel->slug] + $query))->assertOk();
        $preview = $this->actingAs(User::factory()->active()->create())->get(route('admin.preview.hotels', ['hotel' => $hotel->id] + $query))
            ->assertOk()->assertViewIs('catalog.hotel')->assertSee('Privatni pregled')->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame($public->viewData('selected')['id'], $preview->viewData('selected')['id']);
        $this->assertSame($public->viewData('message'), $preview->viewData('message'));
    }

    private function xpath(string $html): \DOMXPath
    {
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new \DOMXPath($document);
    }
}
