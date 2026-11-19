<?php

namespace Database\Factories;

use App\Models\HotelPackage;
use App\Models\HotelRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HotelRate> */
class HotelRateFactory extends Factory
{
    public function definition(): array
    {
        return ['hotel_package_id' => HotelPackage::factory(), 'starts_at' => '2027-06-01', 'ends_at' => '2027-06-04', 'price' => '149.90', 'currency' => 'BAM', 'availability' => 'on_request'];
    }
}
