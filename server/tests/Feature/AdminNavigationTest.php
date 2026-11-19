<?php

namespace Tests\Feature;

use App\Filament\Resources\Hotels\Pages\EditHotel;
use App\Filament\Resources\Hotels\Pages\ListHotels;
use App\Filament\Resources\Trips\Pages\EditTrip;
use App\Filament\Resources\Trips\Pages\ListTrips;
use App\Models\Departure;
use App\Models\Hotel;
use App\Models\HotelPackage;
use App\Models\HotelRate;
use App\Models\Trip;
use App\Models\TripProgram;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2027-05-10 12:00:00', 'Europe/Sarajevo'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Repeater::fake();
        $this->actingAs(User::factory()->active()->create());
    }

    public function test_trip_form_lists_departures_by_date_with_a_summary_line(): void
    {
        $trip = Trip::factory()->create();
        $program = TripProgram::factory()->for($trip)->create(['name' => 'Istanbul — 3 dana']);
        $late = Departure::factory()->for($trip)->create(['starts_at' => '2027-09-01', 'ends_at' => '2027-09-03', 'price' => null]);
        $early = Departure::factory()->for($trip)->create([
            'starts_at' => '2027-06-01', 'ends_at' => '2027-06-03', 'price' => '499.00', 'availability' => 'available',
            'departure_place' => 'Orašje', 'trip_program_id' => $program->id,
        ]);

        $page = Livewire::test(EditTrip::class, ['record' => $trip->id])
            ->assertSeeText('01.06.2027. – 03.06.2027. · 3 dana · 499,00 KM · Raspoloživo · Orašje · Istanbul — 3 dana')
            ->assertSeeText('01.09.2027. – 03.09.2027. · 3 dana · Cijena na upit · Na upit')
            ->assertSeeInOrder(['Osnovni podaci', 'Termini', 'Programi putovanja', 'Opis i uključeno', 'Fotografije']);

        $this->assertSame(['record-'.$early->id, 'record-'.$late->id], array_keys($page->get('data.departures')));
    }

    public function test_departure_can_be_duplicated_and_saved_as_a_new_record(): void
    {
        $trip = Trip::factory()->create();
        $program = TripProgram::factory()->for($trip)->create();
        $departure = Departure::factory()->for($trip)->create(['price' => '299.00', 'departure_place' => 'Tuzla', 'trip_program_id' => $program->id]);

        Livewire::test(EditTrip::class, ['record' => $trip->id])
            ->callAction(TestAction::make('clone')->schemaComponent('departures')->arguments(['item' => 'record-'.$departure->id]))
            ->call('save')->assertHasNoFormErrors();

        $copies = $trip->departures()->orderBy('id')->get();
        $this->assertCount(2, $copies);
        $this->assertSame($departure->id, $copies->first()->id, 'The original departure is kept.');
        $this->assertSame(['299.00', 'Tuzla', $program->id], [$copies->last()->price, $copies->last()->departure_place, $copies->last()->trip_program_id]);
    }

    public function test_trip_and_hotel_tables_show_next_current_date_and_count(): void
    {
        $withDates = Trip::factory()->create(['title' => 'S terminima']);
        Departure::factory()->for($withDates)->create(['starts_at' => '2027-07-01', 'ends_at' => '2027-07-03']);
        Departure::factory()->for($withDates)->create(['starts_at' => '2027-06-01', 'ends_at' => '2027-06-03']);
        Departure::factory()->for($withDates)->create(['starts_at' => '2027-05-20', 'ends_at' => '2027-05-23', 'availability' => 'sold_out']);
        Departure::factory()->for($withDates)->create(['starts_at' => '2027-05-01', 'ends_at' => '2027-05-03']);
        $withoutDates = Trip::factory()->create(['title' => 'Bez termina']);

        Livewire::test(ListTrips::class)
            ->assertSeeText('01.06.2027.')->assertSeeText('2 aktualna termina')->assertSeeText('Nema aktualnih termina')
            ->assertTableColumnDoesNotExist('departures_count')->assertTableColumnDoesNotExist('destination');

        $hotel = Hotel::factory()->create();
        HotelRate::factory()->for(HotelPackage::factory()->for($hotel)->create(), 'hotelPackage')->create(['starts_at' => '2027-08-01', 'ends_at' => '2027-08-05', 'availability' => 'available']);
        Livewire::test(ListHotels::class)->assertSeeText('01.08.2027.')->assertSeeText('1 aktualan termin');
    }

    public function test_lists_default_to_published_and_draft_with_tabs_for_the_rest(): void
    {
        $published = Trip::factory()->create(['status' => 'published']);
        $draft = Trip::factory()->create(['status' => 'draft']);
        $archived = Trip::factory()->create(['status' => 'archived']);

        Livewire::test(ListTrips::class)
            ->assertCanSeeTableRecords([$published, $draft])->assertCanNotSeeTableRecords([$archived])
            ->set('activeTab', 'arhivirana')->assertCanSeeTableRecords([$archived])->assertCanNotSeeTableRecords([$published, $draft])
            ->set('activeTab', 'nacrti')->assertCanSeeTableRecords([$draft])->assertCanNotSeeTableRecords([$published])
            ->set('activeTab', 'sve')->assertCanSeeTableRecords([$published, $draft, $archived]);

        $archivedHotel = Hotel::factory()->create(['status' => 'archived']);
        $draftHotel = Hotel::factory()->create(['status' => 'draft']);
        Livewire::test(ListHotels::class)->assertCanSeeTableRecords([$draftHotel])->assertCanNotSeeTableRecords([$archivedHotel]);
    }

    public function test_row_actions_are_grouped_behind_one_menu_with_edit_kept_visible(): void
    {
        $trip = Trip::factory()->create(['status' => 'published']);

        $list = Livewire::test(ListTrips::class)
            ->assertTableActionVisible('edit', $trip)
            ->assertTableActionVisible('previewSaved', $trip)->assertTableActionVisible('openPublic', $trip)
            ->assertTableActionVisible('delete', $trip);

        $this->assertTrue($list->instance()->getTable()->getColumn('updated_at')->isToggledHiddenByDefault());
    }

    public function test_program_and_day_items_are_labelled_differently(): void
    {
        $trip = Trip::factory()->create();
        TripProgram::factory()->for($trip)->create(['name' => 'Kratki', 'days' => [['title' => 'Polazak', 'description' => ''], ['title' => 'Povratak', 'description' => '']]]);

        Livewire::test(EditTrip::class, ['record' => $trip->id])
            ->assertSeeText('Program 1 · Kratki · 2 dana')->assertSeeText('Dan 1 · Polazak')->assertSeeText('Dan 2 · Povratak')
            ->assertSee('maestro-programs', false)->assertSee('maestro-days', false)->assertSeeText('Dodaj dan u ovaj program');
    }

    public function test_hotel_form_summarizes_packages_and_rates(): void
    {
        $hotel = Hotel::factory()->create();
        $package = HotelPackage::factory()->for($hotel)->create(['name' => 'Polupansion', 'nights' => 7, 'price_basis' => 'room_night']);
        HotelRate::factory()->for($package, 'hotelPackage')->create(['starts_at' => '2027-06-01', 'ends_at' => '2027-06-08', 'price' => '120.00', 'availability' => 'available']);

        Livewire::test(EditHotel::class, ['record' => $hotel->id])
            ->assertSeeText('Polupansion · 7 noćenja · po sobi / noć')
            ->assertSeeText('01.06.2027. – 08.06.2027. · 8 dana · 120,00 KM · Raspoloživo');
    }

    public function test_save_buttons_stay_visible_while_scrolling(): void
    {
        $this->assertTrue(EditTrip::$formActionsAreSticky);
        $this->assertTrue(EditHotel::$formActionsAreSticky);
    }
}
