<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_displays_the_public_catalog(): void
    {
        $this->withoutVite();
        $response = $this->get('/');

        $response->assertOk()->assertSee('Maestro Travel');
    }
}
