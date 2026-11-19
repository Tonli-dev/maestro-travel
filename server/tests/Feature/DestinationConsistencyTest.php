<?php

namespace Tests\Feature;

use App\Filament\Resources\Hotels\Pages\CreateHotel;
use App\Filament\Resources\Trips\Pages\CreateTrip;
use App\Filament\Resources\Trips\Pages\EditTrip;
use App\Models\Hotel;
use App\Models\Trip;
use App\Models\User;
use App\Support\Destinations;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class DestinationConsistencyTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Repeater::fake();
        $this->actingAs(User::factory()->active()->create());
    }

    #[TestWith(['istanbul', 'Istanbul'])]
    #[TestWith(['  ISTANBUL  ', 'Istanbul'])]
    #[TestWith(['Bec', 'Beč'])]
    #[TestWith(['beč', 'Beč'])]
    #[TestWith(['Novi   Sad', 'Novi Sad'])]
    #[TestWith(['Lisabon', 'Lisabon'])]
    public function test_spelling_variants_are_saved_with_the_existing_name(string $typed, string $saved): void
    {
        Trip::factory()->create(['destination' => 'Istanbul']);
        Trip::factory()->create(['destination' => 'Istanbul']);
        Hotel::factory()->create(['destination' => 'Beč']);
        Trip::factory()->create(['destination' => 'Novi Sad']);

        Livewire::test(CreateTrip::class)->fillForm(['title' => 'Novo putovanje', 'destination' => $typed])
            ->call('create')->assertHasNoFormErrors();

        $this->assertSame($saved, Trip::where('title', 'Novo putovanje')->sole()->destination);
    }

    public function test_hotels_share_the_same_destination_names(): void
    {
        Trip::factory()->create(['destination' => 'Istanbul']);

        Livewire::test(CreateHotel::class)->fillForm(['name' => 'Hotel', 'destination' => 'istanbul'])
            ->call('create')->assertHasNoFormErrors();

        $this->assertSame('Istanbul', Hotel::sole()->destination);
    }

    public function test_existing_destinations_are_suggested_once(): void
    {
        Trip::factory()->create(['destination' => 'Istanbul']);
        Trip::factory()->create(['destination' => 'istanbul']);
        Trip::factory()->create(['destination' => 'Istanbul']);
        Hotel::factory()->create(['destination' => 'Beč']);
        Trip::factory()->create(['destination' => 'Obrisano'])->delete();

        $this->assertSame(['Beč', 'Istanbul'], app(Destinations::class)->all());
        Livewire::test(CreateTrip::class)->assertSee('<option value="Istanbul"', false)->assertSee('<option value="Beč"', false);
    }

    public function test_probable_typo_is_flagged_with_a_one_click_fix_and_never_changed_silently(): void
    {
        $trip = Trip::factory()->create(['destination' => 'Istanbul']);

        Livewire::test(CreateTrip::class)->fillForm(['title' => 'Tipfeler', 'destination' => 'Istambul'])
            ->assertSeeText('Postoji slična destinacija „Istanbul“')
            ->callAction(TestAction::make('useSimilarDestination')->schemaComponent('destination'))
            ->assertFormSet(['destination' => 'Istanbul'])
            ->call('create')->assertHasNoFormErrors();
        $this->assertSame('Istanbul', Trip::where('title', 'Tipfeler')->sole()->destination);

        Livewire::test(EditTrip::class, ['record' => $trip->id])->fillForm(['destination' => 'Istambul'])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('Istambul', $trip->fresh()->destination, 'A different word is kept unless the employee accepts the suggestion.');
        $this->assertNull(app(Destinations::class)->similar('Pariz'));
    }

    public function test_suggestion_click_writes_the_shown_name_into_the_field_in_the_browser(): void
    {
        Trip::factory()->create(['destination' => "Sv. Ivan d'Ora <b>"]);

        $html = Livewire::test(CreateTrip::class)->fillForm(['destination' => "Sv. Ivan d'Ora <x>"])->html();

        $this->assertStringContainsString(
            'x-on:click="$wire.set(\'data.destination\', \'Sv. Ivan d\u0027Ora \u003Cb\u003E\')"',
            $html,
            'The button sets the exact suggestion client-side instead of recomputing it from possibly stale server state.',
        );
        $this->assertStringNotContainsString("d'Ora <b>')", $html, 'The suggestion is JSON-encoded, never interpolated raw.');
    }

    public function test_place_without_country_is_matched_to_place_with_country(): void
    {
        Trip::factory()->create(['destination' => 'Istanbul, Turska']);
        $destinations = app(Destinations::class);

        $this->assertSame('Istanbul, Turska', $destinations->similar('Istanbul'));
        $this->assertSame('Istanbul, Turska', $destinations->similar('Istambul'));
        $this->assertSame('Istanbul, Turska', $destinations->similar('Istanbul, Turka'));
        $this->assertNull($destinations->similar('Istanbul, Turska'));
        $this->assertNull($destinations->similar('Ankara'));
    }
}
