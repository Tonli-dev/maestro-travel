<?php

namespace Tests\Feature;

use App\Filament\Resources\Trips\Pages\EditTrip;
use App\Filament\Resources\Trips\Pages\ListTrips;
use App\Models\Departure;
use App\Models\Trip;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Tests\TestCase;

class FeaturedTripsTest extends TestCase
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

    public function test_star_in_trips_table_toggles_featured_with_one_click(): void
    {
        $this->actingAs(User::factory()->active()->create());
        $trip = Trip::factory()->create(['status' => 'published']);

        $list = Livewire::test(ListTrips::class)->assertCanSeeTableRecords([$trip]);
        $list->callTableColumnAction('is_featured', $trip);
        $this->assertTrue($trip->fresh()->is_featured);

        $list->callTableColumnAction('is_featured', $trip);
        $this->assertFalse($trip->fresh()->is_featured);
    }

    public function test_star_is_disabled_for_unpublished_and_deleted_trips(): void
    {
        $this->actingAs(User::factory()->active()->create());
        $draft = Trip::factory()->create(['status' => 'draft']);
        $archived = Trip::factory()->create(['status' => 'archived']);
        $deleted = Trip::factory()->create(['status' => 'published']);
        $deleted->delete();

        $list = Livewire::test(ListTrips::class)->filterTable('trashed', true)
            ->assertSee('Samo objavljeno putovanje može biti izdvojeno.');
        foreach ([$draft, $archived, $deleted] as $trip) {
            $list->callTableColumnAction('is_featured', $trip);
            $this->assertFalse($trip->fresh()->is_featured, $trip->status.' trip must not be starred.');
        }
    }

    public function test_unpublishing_a_trip_removes_its_star(): void
    {
        $this->actingAs(User::factory()->active()->create());
        $trip = Trip::factory()->create(['status' => 'published', 'is_featured' => true]);

        Livewire::test(EditTrip::class, ['record' => $trip->id])
            ->fillForm(['status' => 'archived'])->call('save')->assertHasNoFormErrors();
        $this->assertFalse($trip->fresh()->is_featured);

        $other = Trip::factory()->create(['status' => 'published', 'is_featured' => true]);
        $other->update(['status' => 'draft']);
        $this->assertFalse($other->fresh()->is_featured, 'The model enforces the rule outside the form too.');
        $this->assertFalse(Trip::factory()->create(['status' => 'draft', 'is_featured' => true])->is_featured);
    }

    public function test_table_can_be_filtered_to_featured_trips(): void
    {
        $this->actingAs(User::factory()->active()->create());
        $starred = Trip::factory()->create(['status' => 'published', 'is_featured' => true]);
        $plain = Trip::factory()->create(['status' => 'published']);

        Livewire::test(ListTrips::class)->filterTable('is_featured', true)
            ->assertCanSeeTableRecords([$starred])->assertCanNotSeeTableRecords([$plain]);
    }

    public function test_trip_form_has_no_separate_featured_switch_and_keeps_the_star_on_save(): void
    {
        $this->actingAs(User::factory()->active()->create());
        $trip = Trip::factory()->create(['status' => 'published', 'is_featured' => true]);

        Livewire::test(EditTrip::class, ['record' => $trip->id])
            ->assertFormFieldDoesNotExist('is_featured')
            ->fillForm(['title' => 'Promijenjeno'])->call('save')->assertHasNoFormErrors();

        $this->assertTrue($trip->fresh()->is_featured, 'Saving the form must not clear a star set in the table.');
    }

    public function test_hero_shows_every_starred_current_trip_by_next_date(): void
    {
        $starred = collect(range(1, 5))->map(fn (int $day): Trip => $this->trip(['is_featured' => true, 'title' => 'Izdvojeno '.$day], '2027-06-'.str_pad((string) (10 - $day), 2, '0', STR_PAD_LEFT)));
        $this->trip(['title' => 'Bez zvjezdice'], '2027-05-20');
        $this->trip(['is_featured' => true, 'status' => 'draft', 'title' => 'Nacrt sa zvjezdicom'], '2027-05-20');
        $this->trip(['is_featured' => true, 'title' => 'Prošlo sa zvjezdicom'], '2027-05-01');
        $deleted = $this->trip(['is_featured' => true, 'title' => 'Obrisano sa zvjezdicom'], '2027-05-20');
        $deleted->delete();

        $response = $this->get('/')->assertOk()->assertViewHas('featured', fn (Collection $featured): bool => $featured->pluck('title')->all()
            === $starred->reverse()->pluck('title')->values()->all());
        $response->assertSee('/ 05', false);
        $this->assertSame(1, substr_count($response->getContent(), 'data-hero-panel="0" '));
    }

    public function test_hero_falls_back_to_first_three_current_trips_without_a_current_starred_trip(): void
    {
        $this->trip(['is_featured' => true, 'status' => 'draft'], '2027-06-01');
        $current = collect(range(1, 4))->map(fn (int $day): Trip => $this->trip(['title' => 'Putovanje '.$day], '2027-06-0'.$day));

        $this->get('/')->assertOk()->assertViewHas('featured', fn (Collection $featured): bool => $featured->pluck('title')->all()
            === $current->take(3)->pluck('title')->all());
    }

    public function test_hero_initially_shows_only_the_next_two_side_cards(): void
    {
        foreach (range(1, 5) as $day) {
            $this->trip(['is_featured' => true], '2027-06-0'.$day);
        }

        $html = $this->get('/')->assertOk()->getContent();
        preg_match_all('/<button class="destination-card"[^>]*data-hero-select="(\d+)"[^>]*>/', $html, $cards, PREG_SET_ORDER);
        $visible = collect($cards)->reject(fn (array $card): bool => str_contains($card[0], ' hidden'))->pluck(1)->all();

        $this->assertCount(5, $cards);
        $this->assertSame(['1', '2'], $visible);
    }

    /** @param array<string, mixed> $attributes */
    private function trip(array $attributes, string $startsAt): Trip
    {
        $trip = Trip::factory()->create(['status' => 'published', ...$attributes]);
        Departure::factory()->for($trip)->create(['starts_at' => $startsAt, 'ends_at' => Carbon::parse($startsAt)->addDays(3)->toDateString()]);

        return $trip;
    }
}
