<?php

namespace Tests\Feature;

use App\Filament\Resources\Hotels\Pages\CreateHotel;
use App\Filament\Resources\Hotels\Pages\EditHotel;
use App\Filament\Resources\Labels\Pages\ManageLabels;
use App\Filament\Resources\Trips\Pages\CreateTrip;
use App\Models\Departure;
use App\Models\Hotel;
use App\Models\HotelPackage;
use App\Models\Label;
use App\Models\Trip;
use App\Models\User;
use Database\Seeders\SeasonalShowcaseSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class SeasonalCatalogTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-11-14', 'Europe/Sarajevo'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_category_region_and_all_selected_hotel_amenities_are_shareable(): void
    {
        $this->seed(SeasonalShowcaseSeeder::class);
        $this->seed(SeasonalShowcaseSeeder::class);
        $summer = Label::where('slug', 'ljetovanje-2027')->sole();
        $coast = Label::where('slug', 'jadran')->sole();
        $pool = Label::where('slug', 'bazen')->sole();
        $hotel = Hotel::sole();
        $partial = Hotel::factory()->has(HotelPackage::factory()->hasRates(1), 'packages')->create(['status' => 'published']);
        $partial->labels()->attach([$summer->id, $coast->id, $pool->id]);
        $inland = Trip::factory()->hasDepartures(1)->create(['status' => 'published']);
        $inland->labels()->attach($summer);
        $base = ['category' => $summer->slug, 'region' => $coast->slug];
        $hotels = ['type' => 'hotel'] + $base;
        $this->get(route('home', ['category' => $summer->slug]))->assertViewHas('offers', fn ($offers) => $offers->total() === 2);
        $this->get(route('home', $base))->assertOk()->assertViewHas('offers', fn ($offers) => $offers->total() === 1)
            ->assertSee(route('home', $hotels).'#ponude');
        $this->get(route('home', $hotels))->assertViewHas('offers', fn ($offers) => $offers->total() === 2);
        $this->get(route('home', $hotels + ['amenities' => ['bazen', 'bazen', 'besplatan-parking']]))
            ->assertOk()->assertViewHas('offers', fn ($offers) => $offers->pluck('title')->all() === [$hotel->name])
            ->assertSee(route('home', ['type' => 'trip'] + $base).'#ponude')
            ->assertSee('Ukloni filtar: Besplatan parking')->assertDontSee('id="catalog-transport"', false);
        $this->get(route('home', $hotels + ['q' => 'nema rezultata', 'amenities' => ['bazen']]))
            ->assertOk()->assertSee('Trenutačno nema ponuda')->assertSee('Ukloni filtar: Bazen')->assertSee('Očisti filtre');
        $this->get(route('hotels.show', $hotel->slug))->assertSeeTextInOrder(['Sadržaji hotela', 'Bazen', 'Besplatan parking', 'Privatna plaža']);
        $this->actingAs(User::factory()->active()->create())->get(route('admin.preview.hotels', $hotel))
            ->assertOk()->assertSeeText('Privatna plaža');
    }

    public function test_featured_shortcuts_require_a_current_matching_offer_of_the_active_type(): void
    {
        $advent = Label::factory()->create(['name' => 'Advent 2026', 'featured' => true]);
        foreach (['draft', 'archived', 'deleted', 'expired', 'sold_out'] as $state) {
            $trip = Trip::factory()->create(['status' => in_array($state, ['draft', 'archived']) ? $state : 'published']);
            $trip->labels()->attach($advent);
            Departure::factory()->for($trip)->create([
                'starts_at' => $state === 'expired' ? '2026-10-01' : '2026-12-01',
                'availability' => $state === 'sold_out' ? 'sold_out' : 'available',
            ]);
            if ($state === 'deleted') {
                $trip->delete();
            }
        }
        $this->get('/')->assertDontSee('Advent 2026');
        $published = Trip::factory()->hasDepartures(1)->create(['status' => 'published']);
        $published->labels()->attach($advent);
        $this->get('/')->assertSee('data-category="advent-2026"', false);
        $this->get('/?type=hotel')->assertDontSee('Advent 2026');
        $this->get('/?region=unknown')->assertDontSee('data-category="advent-2026"', false);
        $advent->update(['featured' => false]);
        $this->get('/')->assertSee('Advent 2026')->assertDontSee('data-category="advent-2026"', false);
    }

    public function test_admin_can_create_reuse_rename_and_remove_labels_without_crossing_groups(): void
    {
        $this->actingAs(User::factory()->active()->create());
        $region = Label::resolveName('region', 'Jadran');
        $pool = Label::resolveName('amenity', 'Bazen');
        Livewire::test(CreateHotel::class)
            ->fillForm(['name' => 'Hotel uz more', 'destination' => 'Split', 'regions' => [$region->id], 'amenities' => [$pool->id]])
            ->callAction(TestAction::make('createOption')->schemaComponent('categories'), data: ['name' => 'Ljetovanje 2027'])
            ->assertHasNoFormErrors()->call('create')->assertHasNoFormErrors();
        $hotel = Hotel::sole();
        $summer = $hotel->categories()->sole();
        $this->assertTrue($hotel->regions()->sole()->is($region));
        $this->assertTrue($hotel->amenities()->sole()->is($pool));
        $edit = Livewire::test(EditHotel::class, ['record' => $hotel->id]);
        $edit->callAction(TestAction::make('createOption')->schemaComponent('categories'), data: ['name' => '  LJETOVANJE   2027  '])
            ->call('save')->assertHasNoFormErrors();
        $this->assertDatabaseCount('labels', 3);
        $this->assertDatabaseCount('hotel_labels', 3);
        $edit->fillForm(['regions' => []])->call('save')->assertHasNoFormErrors();
        $this->assertDatabaseCount('hotel_labels', 2);
        $this->assertTrue($hotel->categories()->sole()->is($summer));

        Livewire::test(CreateTrip::class)->fillForm(['title' => 'Primjer', 'destination' => 'Split', 'regions' => [$pool->id]])
            ->call('create')->assertHasFormErrors();
        $this->assertDatabaseCount('trip_labels', 0);
        $admin = Livewire::test(ManageLabels::class);
        $admin->callAction('create', data: ['kind' => 'region', 'name' => 'JADRAN']);
        $this->assertDatabaseCount('labels', 3);
        $admin->callAction(TestAction::make('edit')->table($summer), data: ['name' => 'Ljeto 2027', 'featured' => true])->assertHasNoFormErrors();
        $summer->refresh();
        $this->assertSame('Ljeto 2027', $summer->name);
        $this->assertTrue($summer->featured);
        $this->assertSame('ljetovanje-2027', $summer->slug);
        $hotel->update(['status' => 'published']);
        HotelPackage::factory()->for($hotel)->hasRates(1)->create();
        $this->get('/?type=hotel&category=ljetovanje-2027')->assertSeeText('Ljeto 2027')->assertViewHas('offers', fn ($offers) => $offers->total() === 1);
        $admin->callAction(TestAction::make('delete')->table($pool));
        $this->assertDatabaseMissing('hotel_labels', ['label_id' => $pool->id]);
    }

    public function test_category_featured_icon_toggles_with_one_click_and_other_groups_cannot_be_featured(): void
    {
        $this->actingAs(User::factory()->active()->create());
        $category = Label::resolveName('category', 'Advent 2026');
        $region = Label::resolveName('region', 'Jadran');
        $amenity = Label::resolveName('amenity', 'Bazen');

        $admin = Livewire::test(ManageLabels::class)
            ->assertSee('Kliknite da izdvojite kategoriju kao prečac na naslovnici.')
            ->assertSee('Samo kategorije mogu biti izdvojene.');
        $admin->callTableColumnAction('featured', $category);
        $this->assertTrue($category->fresh()->featured);
        $admin->callTableColumnAction('featured', $category);
        $this->assertFalse($category->fresh()->featured);

        foreach ([$region, $amenity] as $label) {
            $admin->callTableColumnAction('featured', $label);
            $this->assertFalse($label->fresh()->featured, $label->kind.' labels are never featured.');
        }
    }

    public function test_pagination_keeps_filters_and_trip_listing_does_not_query_hotel_inventory(): void
    {
        $coast = Label::resolveName('region', 'Jadran');
        Trip::factory()->count(14)->hasDepartures(1)->create(['status' => 'published', 'destination' => 'Orašje'])
            ->each(fn (Trip $trip) => $trip->labels()->attach($coast));
        DB::enableQueryLog();
        $this->get('/?region=jadran&q=orasje')->assertOk()->assertViewHas('offers', function ($offers): bool {
            $this->assertStringContainsString('region=jadran', $offers->nextPageUrl());

            return $offers->count() === 12 && $offers->total() === 14;
        });
        $this->assertStringNotContainsString('hotel', implode(' ', array_column(DB::getQueryLog(), 'query')));
        DB::disableQueryLog();
        $this->get('/?region=jadran&q=orasje&page=2')->assertViewHas('offers', fn ($offers) => $offers->count() === 2);
    }
}
