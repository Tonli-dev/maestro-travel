<?php

namespace Database\Factories;

use App\Models\Trip;
use App\Models\TripProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TripProgram> */
class TripProgramFactory extends Factory
{
    public function definition(): array
    {
        return ['trip_id' => Trip::factory(), 'name' => 'Ogledni program', 'days' => self::dayList(3)];
    }

    public function days(int $count, string $prefix = 'Dan'): static
    {
        return $this->state(['name' => 'Program '.$count.' dana', 'days' => self::dayList($count, $prefix)]);
    }

    /** @return list<array{title: string, description: string}> */
    private static function dayList(int $count, string $prefix = 'Dan'): array
    {
        return array_map(fn (int $day): array => [
            'title' => $prefix.' '.$day, 'description' => 'Opis za '.mb_strtolower($prefix).' '.$day.'.',
        ], range(1, $count));
    }
}
