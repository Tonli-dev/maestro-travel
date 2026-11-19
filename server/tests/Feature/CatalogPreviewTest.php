<?php

namespace Tests\Feature;

use App\Filament\Resources\Hotels\HotelResource;
use App\Filament\Resources\Hotels\Pages\CreateHotel;
use App\Filament\Resources\Hotels\Pages\EditHotel;
use App\Filament\Resources\Trips\Pages\CreateTrip;
use App\Filament\Resources\Trips\Pages\EditTrip;
use App\Filament\Resources\Trips\TripResource;
use App\Models\Hotel;
use App\Models\HotelPackage;
use App\Models\Trip;
use App\Models\User;
use App\Support\CatalogAdminActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CatalogPreviewTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2027-05-10 12:00:00', 'Europe/Sarajevo'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Repeater::fake();
    }

    #[TestWith(['trip'])]
    #[TestWith(['hotel'])]
    public function test_preview_requires_an_active_employee_and_never_serves_deleted_records(string $type): void
    {
        $record = $type === 'trip' ? Trip::factory()->create() : Hotel::factory()->create();
        $url = CatalogAdminActions::previewUrl($record);
        $this->get($url)->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->actingAs(User::factory()->active()->create())->get($url)->assertOk()
            ->assertSee('Privatni pregled')->assertSee('posljednje spremljene podatke')->assertSee('nema status Objavljeno')
            ->assertSee('nema budućih nepopunjenih termina')->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('Cache-Control', 'no-store, private');
        $record->delete();
        $this->get($url)->assertNotFound();
    }

    #[TestWith(['trip', 'draft'])]
    #[TestWith(['trip', 'archived'])]
    #[TestWith(['hotel', 'draft'])]
    #[TestWith(['hotel', 'archived'])]
    public function test_preview_renders_saved_nonpublic_offers(string $type, string $status): void
    {
        $record = $type === 'trip'
            ? Trip::factory()->hasDepartures(1)->create(['status' => $status])
            : Hotel::factory()->has(HotelPackage::factory()->hasRates(1), 'packages')->create(['status' => $status]);
        $this->actingAs(User::factory()->active()->create());
        $url = CatalogAdminActions::previewUrl($record);
        if ($type === 'hotel') {
            $package = $record->packages->sole();
            $url .= '?'.http_build_query(['package' => $package->id, 'rate' => $package->rates->sole()->id]);
        }
        $this->get($url)->assertOk()->assertSee('Privatni pregled')
            ->assertViewHas('selected', fn ($selected): bool => $selected !== null);
        $this->get(CatalogAdminActions::publicUrl($record))->assertNotFound();
    }

    #[TestWith(['trip'])]
    #[TestWith(['hotel'])]
    public function test_preview_and_public_page_use_the_same_selected_data_and_prices(string $type): void
    {
        $record = $type === 'trip'
            ? Trip::factory()->hasDepartures(2)->create(['status' => 'published'])
            : Hotel::factory()->has(HotelPackage::factory()->hasRates(2), 'packages')->create(['status' => 'published']);
        $option = $type === 'trip' ? $record->departures->last() : $record->packages->sole()->rates->last();
        $query = $type === 'trip' ? ['departure' => $option->id] : ['package' => $option->hotel_package_id, 'rate' => $option->id];
        $this->actingAs(User::factory()->active()->create());
        $preview = $this->get(CatalogAdminActions::previewUrl($record).'?'.http_build_query($query))->assertOk();
        $public = $this->get(CatalogAdminActions::publicUrl($record).'?'.http_build_query($query))->assertOk()->assertDontSee('Privatni pregled');
        $this->assertSame($public->viewData('selected')['id'], $preview->viewData('selected')['id']);
        $this->assertSame($public->viewData('message'), $preview->viewData('message'));
        $this->assertSame($public->viewData('offer')['minima']->pluck('price')->all(), $preview->viewData('offer')['minima']->pluck('price')->all());
    }

    public function test_trip_can_be_created_previewed_published_and_updated_without_stale_prices(): void
    {
        $this->actingAs(User::factory()->active()->create());
        $rate = ['starts_at' => '2027-06-01', 'ends_at' => '2027-06-04', 'price' => '249.90', 'currency' => 'BAM', 'availability' => 'available'];
        Livewire::test(CreateTrip::class)->fillForm([
            'title' => 'Provjera putovanja', 'destination' => 'Istanbul', 'status' => 'draft',
            'departures' => [$rate, [...$rate, 'starts_at' => '2027-07-01', 'ends_at' => '2027-07-04', 'price' => '299.95']],
        ])->call('create')->assertHasNoFormErrors()->assertRedirect(TripResource::getUrl('edit', ['record' => Trip::sole()]));
        $trip = Trip::sole();
        Notification::assertNotified('Ponuda je spremljena');
        $this->assertCount(2, $trip->departures);
        $this->get(CatalogAdminActions::previewUrl($trip))->assertOk()->assertSeeText('249,90 KM');
        $this->get(CatalogAdminActions::publicUrl($trip))->assertNotFound();
        Livewire::test(EditTrip::class, ['record' => $trip->id])
            ->assertActionVisible('previewSaved')->assertActionHidden('openPublic')
            ->fillForm(['status' => 'published'])->call('save')->assertHasNoFormErrors()
            ->assertActionVisible('openPublic');
        $this->get(CatalogAdminActions::publicUrl($trip))->assertOk()->assertSeeText('249,90 KM');
        Livewire::test(EditTrip::class, ['record' => $trip->id])->fillForm([
            'departures' => [[...$rate, 'id' => $trip->departures->first()->id, 'price' => '319.99']],
        ])->call('save')->assertHasNoFormErrors();
        $this->get(CatalogAdminActions::publicUrl($trip))->assertOk()->assertSeeText('319,99 KM')->assertDontSeeText('249,90 KM');
        $trip->refresh();
        $this->assertSame('319.99', $trip->departures->sole()->price);
        $this->get('/admin/trips')->assertOk()->assertSee(CatalogAdminActions::previewUrl($trip), false)->assertSee(CatalogAdminActions::publicUrl($trip), false);
    }

    public function test_hotel_can_be_created_previewed_published_and_updated_with_nested_rates(): void
    {
        $this->actingAs(User::factory()->active()->create());
        $rate = ['starts_at' => '2027-06-01', 'ends_at' => '2027-06-04', 'price' => '149.90', 'currency' => 'BAM', 'availability' => 'on_request'];
        Livewire::test(CreateHotel::class)->fillForm([
            'name' => 'Provjera hotela', 'destination' => 'More', 'status' => 'draft',
            'packages' => [
                ['name' => 'Doručak', 'price_basis' => 'person_package', 'rates' => [$rate]],
                ['name' => 'Polupansion', 'price_basis' => 'room_package', 'rates' => [[...$rate, 'price' => '299.90']]],
            ],
        ])->call('create')->assertHasNoFormErrors()->assertRedirect(HotelResource::getUrl('edit', ['record' => Hotel::sole()]));
        $hotel = Hotel::sole();
        Notification::assertNotified('Ponuda je spremljena');
        $this->assertCount(2, $hotel->packages);
        $this->get(CatalogAdminActions::previewUrl($hotel))->assertOk()->assertSeeText('149,90 KM')->assertSeeText('299,90 KM');
        $this->get(CatalogAdminActions::publicUrl($hotel))->assertNotFound();
        Livewire::test(EditHotel::class, ['record' => $hotel->id])->assertActionHidden('openPublic')
            ->fillForm(['status' => 'published'])->call('save')->assertHasNoFormErrors()->assertActionVisible('openPublic');
        $this->get(CatalogAdminActions::publicUrl($hotel))->assertOk()->assertSeeText('149,90 KM')->assertSeeText('299,90 KM');
        $package = $hotel->packages->last();
        Livewire::test(EditHotel::class, ['record' => $hotel->id])->fillForm([
            'packages' => [['id' => $package->id, 'name' => 'Novi paket', 'price_basis' => 'room_night',
                'rates' => [[...$rate, 'id' => $package->rates->sole()->id, 'price' => '89.99']]]],
        ])->call('save')->assertHasNoFormErrors();
        $this->get(CatalogAdminActions::publicUrl($hotel))->assertOk()->assertSee('Novi paket')->assertSeeText('89,99 KM')->assertSee('po sobi / noć')->assertDontSeeText('299,90 KM');
        $this->get('/admin/hotels')->assertOk()->assertSee(CatalogAdminActions::previewUrl($hotel), false)->assertSee(CatalogAdminActions::publicUrl($hotel), false);
    }

    public function test_unsaved_form_changes_do_not_change_preview_and_invalid_save_has_no_success_notification(): void
    {
        $this->actingAs(User::factory()->active()->create());
        $trip = Trip::factory()->hasDepartures(1)->create(['title' => 'Spremljeni naziv']);
        $edit = Livewire::test(EditTrip::class, ['record' => $trip->id])->fillForm(['title' => 'Nespremljeni naziv']);
        $this->get(CatalogAdminActions::previewUrl($trip))->assertOk()->assertSee('Spremljeni naziv')->assertDontSee('Nespremljeni naziv');
        $edit->fillForm(['title' => ''])->call('save')->assertHasFormErrors(['title']);
        Notification::assertNotNotified();
        $this->assertSame('Spremljeni naziv', $trip->fresh()->title);
    }
}
