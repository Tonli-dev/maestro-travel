<?php

namespace Tests\Feature;

use Database\Seeders\CatalogDemoSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use LogicException;
use Tests\TestCase;

class CatalogSeedTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_default_seeder_creates_no_users_or_demo_offers(): void
    {
        $this->seed();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('trips', 0);
        $this->assertDatabaseCount('hotels', 0);
    }

    public function test_local_demo_seed_is_repeatable_and_keeps_offers_as_drafts(): void
    {
        $this->seed(CatalogDemoSeeder::class);
        $this->seed(CatalogDemoSeeder::class);

        $this->assertDatabaseCount('trips', 1);
        $this->assertDatabaseCount('departures', 2);
        $this->assertDatabaseCount('hotels', 1);
        $this->assertDatabaseCount('hotel_packages', 2);
        $this->assertDatabaseCount('hotel_rates', 2);
        $this->assertDatabaseMissing('trips', ['status' => 'published']);
        $this->assertDatabaseMissing('hotels', ['status' => 'published']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_demo_seed_is_rejected_in_production(): void
    {
        $this->app->instance('env', 'production');

        try {
            app(CatalogDemoSeeder::class)->run();
            $this->fail('Demo seeder must refuse production.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('Ogledni sadrzaj', $exception->getMessage());
        } finally {
            $this->app->instance('env', 'testing');
        }

        $this->assertDatabaseCount('trips', 0);
        $this->assertDatabaseCount('hotels', 0);
    }
}
