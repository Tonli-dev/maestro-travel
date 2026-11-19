<?php

namespace Tests\Feature;

use App\Filament\Resources\Hotels\Pages\CreateHotel;
use App\Filament\Resources\Hotels\Pages\EditHotel;
use App\Filament\Resources\Trips\Pages\CreateTrip;
use App\Filament\Resources\Trips\Pages\EditTrip;
use App\Models\Hotel;
use App\Models\Trip;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CatalogAdminTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Repeater::fake();
    }

    public function test_creates_trip_with_two_departures_and_preserves_decimal_price(): void
    {
        $this->actingAs(User::factory()->active()->create());

        Livewire::test(CreateTrip::class)->fillForm([
            'title' => 'Putovanje', 'destination' => 'Istanbul',
            'departures' => [$this->rate(), [...$this->rate(), 'starts_at' => '2027-07-01', 'ends_at' => '2027-07-05']],
        ])->call('create')->assertHasNoFormErrors();

        $trip = Trip::sole();
        $this->assertSame('draft', $trip->status);
        $this->assertSame('putovanje', $trip->slug);
        $this->assertCount(2, $trip->departures);
        $this->assertSame('249.90', $trip->departures->first()->price);
    }

    public function test_creates_hotel_with_packages_and_rates(): void
    {
        $this->actingAs(User::factory()->active()->create());

        Livewire::test(CreateHotel::class)->fillForm([
            'name' => 'Ogledni hotel', 'destination' => 'Orašje',
            'packages' => [
                ['name' => 'Doručak', 'nights' => 3, 'price_basis' => 'person_package', 'rates' => [$this->rate()]],
                ['name' => 'Polupansion', 'nights' => 3, 'price_basis' => 'room_package', 'rates' => [$this->rate(), [...$this->rate(), 'price' => null]]],
            ],
        ])->call('create')->assertHasNoFormErrors();

        $hotel = Hotel::sole();
        $this->assertSame('draft', $hotel->status);
        $this->assertSame('ogledni-hotel', $hotel->slug);
        $this->assertCount(2, $hotel->packages);
        $this->assertCount(2, $hotel->packages->last()->rates);
        $this->assertNull($hotel->packages->last()->rates->last()->price);
        $this->assertSame('room_package', $hotel->packages->last()->price_basis);
    }

    #[TestWith(['ends_at', '2027-05-01'])]
    #[TestWith(['price', '-1'])]
    #[TestWith(['price', '249.999'])]
    #[TestWith(['currency', 'INVALID'])]
    public function test_invalid_departure_prevents_saving_the_trip(string $field, string $value): void
    {
        $this->actingAs(User::factory()->active()->create());

        Livewire::test(CreateTrip::class)->fillForm([
            'title' => 'Putovanje', 'destination' => 'Istanbul',
            'departures' => [[...$this->rate(), $field => $value]],
        ])->call('create')->assertHasFormErrors(['departures.0.'.$field]);

        $this->assertDatabaseCount('trips', 0);
        $this->assertDatabaseCount('departures', 0);
    }

    public function test_invalid_nested_hotel_rate_prevents_partial_writes(): void
    {
        $this->actingAs(User::factory()->active()->create());

        Livewire::test(CreateHotel::class)->fillForm([
            'name' => 'Hotel', 'destination' => 'Orašje',
            'packages' => [['name' => 'Paket', 'price_basis' => 'person_package', 'rates' => [[...$this->rate(), 'ends_at' => '2027-05-01']]]],
        ])->call('create')->assertHasFormErrors(['packages.0.rates.0.ends_at']);

        $this->assertDatabaseCount('hotels', 0);
        $this->assertDatabaseCount('hotel_packages', 0);
        $this->assertDatabaseCount('hotel_rates', 0);
    }

    public function test_another_employee_can_edit_the_same_trip(): void
    {
        User::factory()->active()->create();
        $trip = Trip::factory()->create();
        $this->actingAs(User::factory()->active()->create());

        Livewire::test(EditTrip::class, ['record' => $trip->id])
            ->fillForm(['title' => 'Promijenjeno putovanje'])->call('save')->assertHasNoFormErrors();

        $this->assertSame('Promijenjeno putovanje', $trip->fresh()->title);
    }

    public function test_deleted_hotel_can_be_restored_without_losing_packages(): void
    {
        $hotel = Hotel::factory()->hasPackages(2)->create();
        $this->actingAs(User::factory()->active()->create());

        Livewire::test(EditHotel::class, ['record' => $hotel->id])->callAction('delete');
        $this->assertSoftDeleted($hotel);
        $this->assertDatabaseCount('hotel_packages', 2);

        Livewire::test(EditHotel::class, ['record' => $hotel->id])->callAction('restore');
        $this->assertNotSoftDeleted($hotel);
    }

    public function test_uploads_a_photo_to_the_public_catalog_disk(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->active()->create());

        Livewire::test(CreateTrip::class)->fillForm([
            'title' => 'Putovanje', 'destination' => 'Istanbul',
            'images' => [UploadedFile::fake()->image('cover.jpg')],
        ])->call('create')->assertHasNoFormErrors();

        $trip = Trip::sole();
        $this->assertCount(1, $trip->images);
        Storage::disk('public')->assertExists($trip->images[0]);
    }

    public function test_rejects_invalid_upload(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->active()->create());

        Livewire::test(CreateTrip::class)->fillForm([
            'title' => 'Putovanje', 'destination' => 'Istanbul',
            'images' => [UploadedFile::fake()->create('document.pdf', 20, 'application/pdf')],
        ])->call('create')->assertHasFormErrors(['images']);

        $this->assertDatabaseCount('trips', 0);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    #[TestWith([CreateTrip::class, Trip::class, 'title'])]
    #[TestWith([CreateHotel::class, Hotel::class, 'name'])]
    public function test_creates_unique_slug_without_employee_input_including_deleted_offers(string $pageClass, string $modelClass, string $nameField): void
    {
        $original = $modelClass::factory()->create(['slug' => 'carobni-bec']);
        $original->delete();
        $modelClass::factory()->create(['slug' => 'carobni-bec-2']);
        $this->actingAs(User::factory()->active()->create());

        Livewire::test($pageClass)->fillForm([
            $nameField => 'Čarobni Beč', 'destination' => 'Beč',
        ])->call('create')->assertHasNoFormErrors();

        $this->assertSame('carobni-bec-3', $modelClass::query()->latest('id')->firstOrFail()->slug);
        $original->restore();
        $this->assertNotSoftDeleted($original);
    }

    #[TestWith([EditTrip::class, Trip::class, 'title'])]
    #[TestWith([EditHotel::class, Hotel::class, 'name'])]
    public function test_renaming_offer_preserves_existing_slug(string $pageClass, string $modelClass, string $nameField): void
    {
        $record = $modelClass::factory()->create(['slug' => 'postojeca-poveznica']);
        $this->actingAs(User::factory()->active()->create());

        Livewire::test($pageClass, ['record' => $record->id])
            ->fillForm([$nameField => 'Novi naziv ponude'])->call('save')->assertHasNoFormErrors();

        $this->assertSame('Novi naziv ponude', $record->fresh()->getAttribute($nameField));
        $this->assertSame('postojeca-poveznica', $record->fresh()->slug);
    }

    #[TestWith([Trip::class, 'title'])]
    #[TestWith([Hotel::class, 'name'])]
    public function test_long_and_symbol_only_names_generate_valid_unique_slugs(string $modelClass, string $nameField): void
    {
        $first = $modelClass::factory()->create([$nameField => str_repeat('a', 255), 'slug' => null]);
        $second = $modelClass::factory()->create([$nameField => str_repeat('a', 255), 'slug' => null]);
        $symbols = $modelClass::factory()->create([$nameField => '***', 'slug' => null]);

        $this->assertSame(str_repeat('a', 255), $first->slug);
        $this->assertSame(str_repeat('a', 253).'-2', $second->slug);
        $this->assertSame('ponuda', $symbols->slug);
    }

    private function rate(): array
    {
        return ['starts_at' => '2027-06-01', 'ends_at' => '2027-06-05', 'price' => '249.90', 'currency' => 'BAM', 'availability' => 'on_request'];
    }
}
