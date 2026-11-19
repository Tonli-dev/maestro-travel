<?php

namespace Database\Factories;

use App\Models\InstagramPost;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<InstagramPost> */
class InstagramPostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'url' => 'https://www.instagram.com/p/'.Str::random(11).'/',
            'title' => 'Objava '.fake()->unique()->numberBetween(1, 9999),
            'description' => 'Kratki opis objave.',
            'posted_on' => '2026-10-20',
            'sort' => 0,
        ];
    }
}
