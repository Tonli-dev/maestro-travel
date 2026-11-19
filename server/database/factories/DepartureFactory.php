<?php

namespace Database\Factories;

use App\Models\Departure;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Departure> */
class DepartureFactory extends Factory
{
    public function definition(): array
    {
        return ['trip_id' => Trip::factory(), 'starts_at' => '2027-06-01', 'ends_at' => '2027-06-05', 'price' => '249.90', 'currency' => 'BAM', 'availability' => 'on_request'];
    }
}
