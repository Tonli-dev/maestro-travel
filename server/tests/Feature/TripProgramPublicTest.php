<?php

namespace Tests\Feature;

use App\Models\Departure;
use App\Models\Trip;
use App\Models\TripProgram;
use App\Models\User;
use App\Support\CatalogAdminActions;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TripProgramPublicTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2027-05-10 12:00:00', 'Europe/Sarajevo'));
        config(['contact.whatsapp_number' => '387600000000']);
    }

    public function test_selected_departure_determines_the_three_or_five_day_program_and_inquiry(): void
    {
        [$trip, $short, $long] = $this->istanbul();
        $first = Departure::factory()->for($trip)->create(['starts_at' => '2027-06-01', 'ends_at' => '2027-06-03', 'trip_program_id' => $short->id, 'price' => '299.00']);
        $second = Departure::factory()->for($trip)->create(['starts_at' => '2027-07-01', 'ends_at' => '2027-07-05', 'trip_program_id' => $long->id, 'price' => '499.00']);
        $url = route('trips.show', $trip->slug);

        $this->get($url)->assertOk()
            ->assertViewHas('selected', fn (array $selected): bool => $selected['id'] === $first->id)
            ->assertSeeInOrder(['data-trip-inquiry', 'Program', 'Istanbul — 3 dana', 'id="program"', 'Istanbul — 3 dana · 3 dana', '1. dan · Kratki 1', '3. dan · Kratki 3'], false)
            ->assertDontSee('Dugi 4')->assertDontSee('Stari tekst');

        $response = $this->get($url.'?departure='.$second->id)->assertOk()
            ->assertSeeText('Istanbul — 5 dana · 5 dana')->assertSeeText('5. dan · Dugi 5')->assertDontSee('Kratki 1')
            ->assertSee('href="#program"', false);
        $this->assertStringContainsString('Program: Istanbul — 5 dana.', $response->viewData('message'));
        $this->assertStringContainsString('Program: Istanbul — 5 dana.', rawurldecode($response->viewData('whatsapp')));
        $this->assertStringContainsString('Termin: 01.07.2027. – 05.07.2027. Program: Istanbul — 5 dana. 499,00 KM po osobi', $response->viewData('message'));
        $this->assertStringContainsString('Program: Istanbul — 5 dana, 5 dana.', $this->announcement($response));
        foreach ($this->whatsappLinks($response) as $href) {
            $this->assertSame($response->viewData('whatsapp'), $href, 'Floating and inline contact use the same program context.');
        }
    }

    public function test_one_program_edit_is_reflected_in_every_departure_that_uses_it(): void
    {
        [$trip, $short] = $this->istanbul();
        $departures = Departure::factory()->for($trip)->count(2)->sequence(
            ['starts_at' => '2027-06-01', 'ends_at' => '2027-06-03'],
            ['starts_at' => '2027-09-01', 'ends_at' => '2027-09-03'],
        )->create(['trip_program_id' => $short->id]);
        $short->update(['days' => [...$short->days, ['title' => 'Novi zajednički dan', 'description' => '']]]);

        foreach ($departures as $departure) {
            $this->get(route('trips.show', ['trip' => $trip->slug, 'departure' => $departure->id]))->assertOk()
                ->assertSeeText('4. dan · Novi zajednički dan')->assertSeeText('Istanbul — 3 dana · 4 dana');
        }
    }

    public function test_unassigned_departures_keep_the_legacy_program_rendering(): void
    {
        [$trip, $short] = $this->istanbul();
        $legacy = Departure::factory()->for($trip)->create(['starts_at' => '2027-06-01', 'ends_at' => '2027-06-03']);
        $structured = Departure::factory()->for($trip)->create(['starts_at' => '2027-07-01', 'ends_at' => '2027-07-03', 'trip_program_id' => $short->id]);
        $url = route('trips.show', $trip->slug);

        $response = $this->get($url.'?departure='.$legacy->id)->assertOk()
            ->assertSee('<h3>1. Prvi dan</h3><p>Stari tekst.</p>', false)->assertSee('<p>Slobodan odlomak bez dana.</p>', false)
            ->assertDontSee('Kratki 1')->assertSee('data-itinerary="legacy"', false);
        $this->assertStringNotContainsString('Program:', $response->viewData('message'));
        $this->get($url.'?departure='.$structured->id)->assertOk()->assertSeeText('Kratki 1')->assertDontSee('Stari tekst');
    }

    public function test_invalid_requests_fall_back_to_the_first_eligible_departure_and_its_program(): void
    {
        [$trip, $short, $long] = $this->istanbul();
        $fallback = Departure::factory()->for($trip)->create(['starts_at' => '2027-06-01', 'ends_at' => '2027-06-05', 'trip_program_id' => $long->id]);
        $past = Departure::factory()->for($trip)->create(['starts_at' => '2027-05-01', 'ends_at' => '2027-05-03', 'trip_program_id' => $short->id]);
        $soldOut = Departure::factory()->for($trip)->create(['starts_at' => '2027-08-01', 'ends_at' => '2027-08-03', 'availability' => 'sold_out', 'trip_program_id' => $short->id]);
        $removed = Departure::factory()->for($trip)->create(['starts_at' => '2027-09-01', 'ends_at' => '2027-09-03', 'trip_program_id' => $short->id]);
        $removedId = $removed->id;
        $removed->delete();
        $otherTrip = Trip::factory()->create(['status' => 'published']);
        $foreign = Departure::factory()->for($otherTrip)->create([
            'trip_program_id' => TripProgram::factory()->for($otherTrip)->create(['days' => [['title' => 'Tuđi dan', 'description' => '']]])->id,
        ]);

        foreach ([$past->id, $soldOut->id, $removedId, $foreign->id, 'abc', '1e3'] as $requested) {
            $this->get(route('trips.show', $trip->slug).'?departure='.$requested)->assertOk()
                ->assertSee('Odabrani termin nije dostupan')
                ->assertViewHas('selected', fn (array $selected): bool => $selected['id'] === $fallback->id)
                ->assertSeeText('5. dan · Dugi 5')->assertDontSee('Kratki 1')->assertDontSee('Tuđi dan');
        }
        $this->get(route('trips.show', $trip->slug).'?departure[]='.$fallback->id)->assertOk()->assertSeeText('Dugi 1');
    }

    public function test_no_current_departure_states_never_show_an_unrelated_program(): void
    {
        [$trip] = $this->istanbul();
        Departure::factory()->for($trip)->create(['starts_at' => '2027-05-01', 'ends_at' => '2027-05-03', 'trip_program_id' => $trip->programs->first()->id]);
        $url = route('trips.show', $trip->slug);

        $this->get($url)->assertOk()->assertViewHas('selected', null)
            ->assertSee('Trenutačno nema aktualnih termina')->assertSee('Stari tekst.')->assertDontSee('Kratki 1');

        $trip->update(['program' => null]);
        $this->get($url)->assertOk()->assertSee('Program ovisi o terminu putovanja')
            ->assertDontSee('Kratki 1')->assertDontSee('Dugi 1')->assertSee('Prijevoz autobusom');

        $plain = Trip::factory()->create(['status' => 'published', 'program' => null, 'included' => null, 'excluded' => null]);
        Departure::factory()->for($plain)->create();
        $this->get(route('trips.show', $plain->slug))->assertOk()
            ->assertDontSee('id="program"', false)->assertDontSee('href="#program"', false)->assertDontSee('Vaše putovanje, dan po dan.');

        $costsOnly = Trip::factory()->create(['status' => 'published', 'program' => null, 'included' => 'Smještaj']);
        Departure::factory()->for($costsOnly)->create();
        $this->get(route('trips.show', $costsOnly->slug))->assertOk()
            ->assertSee('id="program"', false)->assertSee('Smještaj')->assertDontSee('Vaše putovanje, dan po dan.')->assertDontSee('href="#program"', false);
    }

    public function test_corrupt_or_foreign_program_links_render_an_honest_missing_state(): void
    {
        [$trip, $short] = $this->istanbul();
        $departure = Departure::factory()->for($trip)->create(['trip_program_id' => $short->id]);
        $foreignProgram = TripProgram::factory()->create(['days' => [['title' => 'Tuđi dan', 'description' => '']]]);
        $url = route('trips.show', $trip->slug);

        DB::table('departures')->where('id', $departure->id)->update(['trip_program_id' => $foreignProgram->id]);
        $response = $this->get($url)->assertOk()->assertSee('Program za odabrani termin trenutačno nije dostupan')
            ->assertDontSee('Tuđi dan')->assertDontSee('Stari tekst')->assertSee('data-itinerary="missing"', false);
        $this->assertStringNotContainsString('Program:', $response->viewData('message'));

        foreach (['[]', '{"title":"x"}', '[{"title":["x"]}]', '[{"title":"  ","description":"x"}]', 'null'] as $payload) {
            DB::table('departures')->where('id', $departure->id)->update(['trip_program_id' => $short->id]);
            DB::table('trip_programs')->where('id', $short->id)->update(['days' => $payload]);
            $content = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('Program za odabrani termin trenutačno nije dostupan', $content, 'Payload: '.$payload);
            $this->assertStringNotContainsString('Stari tekst', $content);
        }
    }

    public function test_program_text_is_escaped_and_keeps_paragraph_breaks(): void
    {
        $payload = '<script>alert("xss")</script>';
        $trip = Trip::factory()->create(['status' => 'published']);
        $program = TripProgram::factory()->for($trip)->create(['name' => $payload, 'days' => [
            ['title' => $payload, 'description' => "Prvi odlomak.\n\nDrugi <b>odlomak</b>."],
        ]]);
        Departure::factory()->for($trip)->create(['trip_program_id' => $program->id]);

        $this->get(route('trips.show', $trip->slug))->assertOk()
            ->assertDontSee($payload, false)->assertDontSee('<b>odlomak</b>', false)
            ->assertSee(e($payload), false)
            ->assertSee("<p>Prvi odlomak.\n\nDrugi &lt;b&gt;odlomak&lt;/b&gt;.</p>", false);
    }

    public function test_preview_uses_the_same_saved_program_selection_with_private_headers(): void
    {
        [$trip, $short, $long] = $this->istanbul();
        $trip->update(['status' => 'draft']);
        Departure::factory()->for($trip)->create(['starts_at' => '2027-06-01', 'ends_at' => '2027-06-03', 'trip_program_id' => $short->id]);
        $second = Departure::factory()->for($trip)->create(['starts_at' => '2027-07-01', 'ends_at' => '2027-07-05', 'trip_program_id' => $long->id]);
        $url = CatalogAdminActions::previewUrl($trip).'?departure='.$second->id;

        $this->get($url)->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $preview = $this->actingAs(User::factory()->active()->create())->get($url)->assertOk()
            ->assertSee('Privatni pregled')->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertHeader('Cache-Control', 'no-store, private')
            ->assertSeeText('5. dan · Dugi 5')->assertDontSee('Kratki 1');

        $trip->update(['status' => 'published']);
        $public = $this->get(route('trips.show', ['trip' => $trip->slug, 'departure' => $second->id]))->assertOk();
        $this->assertSame($public->viewData('itinerary'), $preview->viewData('itinerary'));
        $this->assertSame($public->viewData('message'), $preview->viewData('message'));
    }

    public function test_listing_does_not_query_programs(): void
    {
        [$trip, $short] = $this->istanbul();
        Departure::factory()->for($trip)->create(['trip_program_id' => $short->id]);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->get('/')->assertOk()->assertSee('Istanbul');

        $this->assertEmpty(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'trip_programs')));
    }

    public function test_detail_eager_loads_programs_for_all_departures(): void
    {
        [$trip, $short, $long] = $this->istanbul();
        Departure::factory()->for($trip)->count(6)->sequence(fn ($sequence): array => [
            'starts_at' => '2027-06-0'.($sequence->index + 1), 'ends_at' => '2027-06-0'.($sequence->index + 3),
            'trip_program_id' => $sequence->index % 2 ? $long->id : $short->id,
        ])->create();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->get(route('trips.show', $trip->slug))->assertOk();

        $this->assertCount(1, array_filter($queries, fn (string $sql): bool => str_contains($sql, 'from "trip_programs"')));
    }

    /** @return array{0: Trip, 1: TripProgram, 2: TripProgram} */
    private function istanbul(): array
    {
        $trip = Trip::factory()->create([
            'status' => 'published', 'title' => 'Istanbul',
            'program' => "Prvi dan|Stari tekst.\nSlobodan odlomak bez dana.", 'included' => 'Prijevoz autobusom',
        ]);
        $short = TripProgram::factory()->for($trip)->days(3, 'Kratki')->create(['name' => 'Istanbul — 3 dana']);
        $long = TripProgram::factory()->for($trip)->days(5, 'Dugi')->create(['name' => 'Istanbul — 5 dana']);

        return [$trip->fresh(), $short, $long];
    }

    private function announcement(TestResponse $response): string
    {
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());

        return (new \DOMXPath($document))->query('//*[@data-selection-announcement]')->item(0)->getAttribute('data-selection-announcement');
    }

    /** @return list<string> */
    private function whatsappLinks(TestResponse $response): array
    {
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        $links = [];
        foreach ((new \DOMXPath($document))->query('//a[@data-whatsapp-context or @id="whatsapp-floating"]') as $link) {
            $links[] = $link->getAttribute('href');
        }
        $this->assertCount(2, $links);

        return $links;
    }
}
