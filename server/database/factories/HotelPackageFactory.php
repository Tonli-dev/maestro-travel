<?php

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\HotelPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HotelPackage> */
class HotelPackageFactory extends Factory
{
    public function definition(): array
    {
        return ['hotel_id' => Hotel::factory(), 'name' => 'Ogledni paket', 'nights' => 3, 'price_basis' => 'person_package'];
    }
}
