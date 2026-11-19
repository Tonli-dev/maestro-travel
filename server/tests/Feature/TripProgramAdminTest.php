<?php

namespace Tests\Feature;

use App\Filament\Resources\Trips\Pages\CreateTrip;
use App\Filament\Resources\Trips\Pages\EditTrip;
use App\Models\Departure;
use App\Models\Trip;
use App\Models\TripProgram;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class TripProgramAdminTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Repeater::fake();
        $this->actingAs(User::factory()->active()->create());
    }

    public function test_creates_independent_three_and_five_day_programs_with_paragraphs(): void
    {
        Livewire::test(CreateTrip::class)->fillForm([
            'title' => 'Istanbul', 'destination' => 'Istanbul',
            'departures' => [$this->rate()],
            'programs' => [
                ['name' => 'Istanbul — 3 dana', 'days' => $this->days(['Polazak', 'Razgled', 'Povratak'])],
                ['name' => 'Istanbul — 5 dana', 'days' => [
                    ['title' => 'Polazak', 'description' => "Prvi odlomak.\r\n\r\nDrugi odlomak."],
                    ...$this->days(['Razgled', 'Bospor', 'Princevi otoci', 'Povratak']),
                ]],
            ],
        ])->call('create')->assertHasNoFormErrors();

        $trip = Trip::sole();
        $short = $trip->programs->firstWhere('name', 'Istanbul — 3 dana');
        $long = $trip->programs->firstWhere('name', 'Istanbul — 5 dana');
        $this->assertSame(3, $short->dayCount());
        $this->assertSame(5, $long->dayCount());
        $this->assertSame(['Polazak', 'Razgled', 'Povratak'], array_column($short->days, 'title'));
        $this->assertSame(range(0, 4), array_keys($long->fresh()->days), 'Days are stored as a sequential list.');
        $this->assertSame("Prvi odlomak.\n\nDrugi odlomak.", $long->fresh()->days[0]['description']);
        $this->assertSame(['title', 'description'], array_keys($long->days[0]));
        $this->assertNull($trip->departures->sole()->trip_program_id, 'New programs are assigned after saving, not by temporary keys.');
    }

    public function test_program_selector_is_disabled_until_the_trip_is_saved_and_rejects_tampered_ids(): void
    {
        $foreign = TripProgram::factory()->create();

        Livewire::test(CreateTrip::class)
            ->fillForm([
                'title' => 'Istanbul', 'destination' => 'Istanbul',
                'departures' => [[...$this->rate(), 'trip_program_id' => $foreign->id]],
            ])->assertSee('Spremite putovanje i njegove programe')
            ->call('create')->assertHasFormErrors(['departures.0.trip_program_id']);

        $this->assertDatabaseMissing('trips', ['title' => 'Istanbul']);
        $this->assertDatabaseCount('departures', 0);
    }

    public function test_departures_reuse_one_saved_program_and_keep_their_other_fields(): void
    {
        $trip = Trip::factory()->create();
        $short = TripProgram::factory()->for($trip)->days(3)->create(['name' => 'Kratki']);
        $long = TripProgram::factory()->for($trip)->days(5)->create(['name' => 'Dugi']);
        [$first, $second, $third] = Departure::factory()->for($trip)->count(3)->sequence(
            ['starts_at' => '2027-06-01', 'ends_at' => '2027-06-03', 'departure_place' => 'Orašje'],
            ['starts_at' => '2027-07-01', 'ends_at' => '2027-07-05', 'departure_place' => 'Tuzla'],
            ['starts_at' => '2027-08-01', 'ends_at' => '2027-08-03', 'price' => null],
        )->create();

        $this->edit($trip)
            ->assertSee('Kratki · 3 dana')->assertSee('Dugi · 5 dana')
            ->fillForm([
                'departures' => [
                    'record-'.$first->id => [...$this->departureState($first), 'trip_program_id' => $short->id],
                    'record-'.$second->id => [...$this->departureState($second), 'trip_program_id' => $long->id],
                    'record-'.$third->id => [...$this->departureState($third), 'trip_program_id' => $short->id],
                ],
            ])->call('save')->assertHasNoFormErrors();

        $this->assertSame([$short->id, $long->id, $short->id], $trip->departures()->orderBy('id')->pluck('trip_program_id')->all());
        $this->assertSame(['Orašje', 'Tuzla', null], $trip->departures()->orderBy('id')->pluck('departure_place')->all());
        $this->assertNull($third->fresh()->price);
        $this->assertSame(2, $short->departures()->count());
        $this->assertSame(2, TripProgram::count(), 'Reusing a program never copies it.');
    }

    public function test_days_can_be_reordered_removed_and_added_with_sequential_storage(): void
    {
        $trip = Trip::factory()->create();
        $program = TripProgram::factory()->for($trip)->create(['name' => 'Program', 'days' => $this->days(['A', 'B', 'C'])]);

        $this->edit($trip)->set('data.programs', ['record-'.$program->id => ['name' => 'Program', 'days' => [
            'x' => ['title' => 'C', 'description' => 'Treći'],
            'y' => ['title' => 'A', 'description' => "Prvi\n\nnastavak"],
            'z' => ['title' => 'D', 'description' => ''],
        ]]])->call('save')->assertHasNoFormErrors();

        $days = $program->fresh()->days;
        $this->assertSame(['C', 'A', 'D'], array_column($days, 'title'));
        $this->assertSame([0, 1, 2], array_keys($days));
        $this->assertSame("Prvi\n\nnastavak", $days[1]['description']);
        $this->assertSame($program->id, TripProgram::sole()->id, 'Editing keeps the same program record.');
    }

    public function test_program_requires_a_name_and_well_formed_days(): void
    {
        $trip = Trip::factory()->create();

        $this->edit($trip)->fillForm(['programs' => [['name' => '', 'days' => []]]])
            ->call('save')->assertHasFormErrors(['programs.0.name', 'programs.0.days']);
        $this->edit($trip)->fillForm(['programs' => [['name' => 'Program', 'days' => [['title' => ['nested' => 'x'], 'description' => ['y']]]]]])
            ->call('save')->assertHasFormErrors(['programs.0.days.0.title', 'programs.0.days.0.description']);
        $this->edit($trip)->fillForm(['programs' => [['name' => 'Program', 'days' => [['title' => str_repeat('a', 161), 'description' => '']]]]])
            ->call('save')->assertHasFormErrors(['programs.0.days.0.title']);

        $this->assertDatabaseCount('trip_programs', 0);
    }

    public function test_foreign_or_tampered_program_id_is_rejected_without_partial_writes(): void
    {
        $trip = Trip::factory()->create(['title' => 'Izvorno']);
        $departure = Departure::factory()->for($trip)->create();
        $foreign = TripProgram::factory()->create();

        foreach ([$foreign->id, 999999, 'abc'] as $tampered) {
            $this->edit($trip)->fillForm([
                'title' => 'Promijenjeno',
                'programs' => [['name' => 'Novi program', 'days' => $this->days(['Dan'])]],
                'departures' => ['record-'.$departure->id => [...$this->departureState($departure), 'trip_program_id' => $tampered]],
            ])->call('save')->assertHasFormErrors(['departures.record-'.$departure->id.'.trip_program_id']);
        }

        $this->assertSame('Izvorno', $trip->fresh()->title);
        $this->assertNull($departure->fresh()->trip_program_id);
        $this->assertSame(0, $trip->programs()->count());
    }

    public function test_removing_an_assigned_program_is_blocked_and_preserves_content(): void
    {
        $trip = Trip::factory()->create();
        $program = TripProgram::factory()->for($trip)->create(['name' => 'Kratki']);
        $departure = Departure::factory()->for($trip)->create(['trip_program_id' => $program->id, 'availability' => 'sold_out', 'starts_at' => '2020-01-01', 'ends_at' => '2020-01-03']);

        $this->edit($trip)->set('data.programs', [])->call('save')
            ->assertHasFormErrors(['programs'])->assertSee('Program „Kratki“ koristi 1 termin');

        $this->assertModelExists($program);
        $this->assertSame($program->id, $departure->fresh()->trip_program_id);
        $this->assertDatabaseCount('departures', 1);
    }

    public function test_program_can_be_removed_while_its_departures_are_moved_in_the_same_save(): void
    {
        $trip = Trip::factory()->create();
        $old = TripProgram::factory()->for($trip)->create(['name' => 'Stari']);
        $new = TripProgram::factory()->for($trip)->create(['name' => 'Novi']);
        $moved = Departure::factory()->for($trip)->create(['trip_program_id' => $old->id]);
        $cleared = Departure::factory()->for($trip)->create(['trip_program_id' => $old->id, 'starts_at' => '2027-09-01', 'ends_at' => '2027-09-05']);

        $this->edit($trip)->set('data.programs', ['record-'.$new->id => ['name' => 'Novi', 'days' => $new->days]])->fillForm([
            'departures' => [
                'record-'.$moved->id => [...$this->departureState($moved), 'trip_program_id' => $new->id],
                'record-'.$cleared->id => [...$this->departureState($cleared), 'trip_program_id' => null],
            ],
        ])->call('save')->assertHasNoFormErrors();

        $this->assertModelMissing($old);
        $this->assertSame($new->id, $moved->fresh()->trip_program_id);
        $this->assertNull($cleared->fresh()->trip_program_id);
    }

    public function test_database_restricts_deleting_a_program_that_a_departure_uses(): void
    {
        $program = TripProgram::factory()->create();
        Departure::factory()->for($program->trip)->create(['trip_program_id' => $program->id]);

        $this->expectException(QueryException::class);
        $program->delete();
    }

    public function test_day_count_mismatch_warns_without_changing_dates_or_days(): void
    {
        $trip = Trip::factory()->create();
        $program = TripProgram::factory()->for($trip)->days(5)->create(['name' => 'Dugi']);
        $departure = Departure::factory()->for($trip)->create(['starts_at' => '2027-06-01', 'ends_at' => '2027-06-03', 'trip_program_id' => $program->id]);
        $matching = Departure::factory()->for($trip)->create(['starts_at' => '2027-07-01', 'ends_at' => '2027-07-05', 'trip_program_id' => $program->id]);

        $this->edit($trip)->assertSeeText('program ima 5 dana, a termin prema datumima traje 3 dana')
            ->set('data.departures.record-'.$departure->id.'.ends_at', '2027-06-05')
            ->assertDontSeeText('a termin prema datumima traje')
            ->set('data.departures.record-'.$departure->id.'.ends_at', '2027-06-03')
            ->call('save')->assertHasNoFormErrors();

        $this->assertSame('2027-06-03', $departure->fresh()->ends_at->toDateString());
        $this->assertSame('2027-07-05', $matching->fresh()->ends_at->toDateString());
        $this->assertSame(5, $program->fresh()->dayCount());
    }

    public function test_soft_delete_and_restore_preserve_programs_and_assignments_and_force_delete_is_clean(): void
    {
        $trip = Trip::factory()->create();
        $program = TripProgram::factory()->for($trip)->create();
        $departure = Departure::factory()->for($trip)->create(['trip_program_id' => $program->id]);

        $this->edit($trip)->callAction('delete');
        $this->assertSoftDeleted($trip);
        $this->edit($trip)->callAction('restore');
        $this->assertNotSoftDeleted($trip);
        $this->assertSame($program->id, $departure->fresh()->trip_program_id);
        $this->assertModelExists($program);

        $trip->forceDelete();
        $this->assertDatabaseCount('trip_programs', 0);
        $this->assertDatabaseCount('departures', 0);
    }

    public function test_legacy_free_text_program_remains_editable(): void
    {
        $trip = Trip::factory()->create(['program' => "Prvi dan|Dolazak\nDrugi dan|Povratak"]);

        $this->edit($trip)->assertSee('Prijelazni program')
            ->assertFormSet(['program' => "Prvi dan|Dolazak\nDrugi dan|Povratak"])
            ->fillForm(['program' => 'Prvi dan|Izmijenjeno'])->call('save')->assertHasNoFormErrors();

        $this->assertSame('Prvi dan|Izmijenjeno', $trip->fresh()->program);
    }

    private function edit(Trip $trip): Testable
    {
        return Livewire::test(EditTrip::class, ['record' => $trip->id]);
    }

    /** @return array<string, mixed> */
    private function departureState(Departure $departure): array
    {
        return [
            'starts_at' => $departure->starts_at->toDateString(), 'ends_at' => $departure->ends_at->toDateString(),
            'price' => $departure->price, 'currency' => $departure->currency, 'availability' => $departure->availability,
            'departure_place' => $departure->departure_place, 'trip_program_id' => $departure->trip_program_id,
        ];
    }

    /**
     * @param  list<string>  $titles
     * @return list<array{title: string, description: string}>
     */
    private function days(array $titles): array
    {
        return array_map(fn (string $title): array => ['title' => $title, 'description' => 'Opis: '.$title], $titles);
    }

    /** @return array<string, string> */
    private function rate(): array
    {
        return ['starts_at' => '2027-06-01', 'ends_at' => '2027-06-05', 'price' => '249.90', 'currency' => 'BAM', 'availability' => 'on_request'];
    }
}
