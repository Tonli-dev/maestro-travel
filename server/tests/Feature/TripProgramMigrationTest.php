<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Runs against the in-memory test database only; never against the working database.
 */
class TripProgramMigrationTest extends TestCase
{
    private const MIGRATION = '2026_11_18_223112_create_trip_programs_and_assign_departures';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2027-05-10 12:00:00', 'Europe/Sarajevo'));
        $this->artisan('migrate', ['--step' => true])->assertSuccessful();
    }

    public function test_migration_preserves_legacy_trips_departures_and_slugs(): void
    {
        $this->rollBackProgramMigration();
        $this->assertFalse(Schema::hasTable('trip_programs'));
        $this->assertFalse(Schema::hasColumn('departures', 'trip_program_id'));

        $legacyProgram = "Prvi dan|Dolazak u Istanbul\nDrugi dan|Razgled\nSlobodan odlomak.";
        $tripId = DB::table('trips')->insertGetId([
            'title' => 'Istanbul', 'slug' => 'istanbul-stari-link', 'destination' => 'Istanbul', 'status' => 'published',
            'program' => $legacyProgram, 'included' => 'Prijevoz', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $departureId = DB::table('departures')->insertGetId([
            'trip_id' => $tripId, 'starts_at' => '2027-06-01', 'ends_at' => '2027-06-03', 'price' => '249.90',
            'currency' => 'BAM', 'availability' => 'available', 'departure_place' => 'Orašje', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('migrate')->assertSuccessful();
        $this->assertTrue(DB::table('migrations')->where('migration', self::MIGRATION)->exists());

        $this->assertSame($legacyProgram, DB::table('trips')->where('id', $tripId)->value('program'));
        $this->assertSame('istanbul-stari-link', DB::table('trips')->where('id', $tripId)->value('slug'));
        $departure = DB::table('departures')->where('id', $departureId)->first();
        $this->assertNull($departure->trip_program_id);
        $this->assertSame('Orašje', $departure->departure_place);
        $this->assertSame(0, DB::table('trip_programs')->count(), 'Legacy text is not converted automatically.');

        $this->get('/putovanja/istanbul-stari-link?departure='.$departureId)->assertOk()
            ->assertSee('<h3>1. Prvi dan</h3><p>Dolazak u Istanbul</p>', false)->assertSee('<p>Slobodan odlomak.</p>', false)
            ->assertSee('href="#program"', false);
    }

    public function test_rollback_removes_only_the_new_structures(): void
    {
        $tripId = DB::table('trips')->insertGetId(['title' => 'Putovanje', 'slug' => 'putovanje', 'destination' => 'Orašje', 'status' => 'draft', 'program' => 'Tekst']);
        $programId = DB::table('trip_programs')->insertGetId(['trip_id' => $tripId, 'name' => 'Program', 'days' => '[{"title":"Dan","description":""}]']);
        DB::table('departures')->insert(['trip_id' => $tripId, 'trip_program_id' => $programId, 'starts_at' => '2027-06-01', 'ends_at' => '2027-06-03']);

        $this->rollBackProgramMigration();

        $this->assertFalse(Schema::hasTable('trip_programs'));
        $this->assertSame(1, DB::table('departures')->where('trip_id', $tripId)->count());
        $this->assertSame('Tekst', DB::table('trips')->where('id', $tripId)->value('program'));
    }

    /** Rolls back the program migration and any later ones (each ran in its own batch). */
    private function rollBackProgramMigration(): void
    {
        $steps = DB::table('migrations')->where('migration', '>=', self::MIGRATION)->count();
        $this->artisan('migrate:rollback', ['--step' => $steps])->assertSuccessful();
    }
}
