<?php

namespace App\Models;

use Database\Factories\TripProgramFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/**
 * A reusable itinerary variant of one trip (for example a 3-day and a 5-day version).
 * Day order is the order of the `days` list; day numbers are derived, never stored.
 *
 * @property list<array{title: string, description: string}> $days
 */
#[Fillable(['name', 'days'])]
class TripProgram extends Model
{
    /** @use HasFactory<TripProgramFactory> */
    use HasFactory;

    public const MAX_DAYS = 31;

    protected static function booted(): void
    {
        static::saving(function (TripProgram $program): void {
            $program->days = self::normalizeDays($program->days);
        });
    }

    protected function casts(): array
    {
        return [
            'days' => 'array',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function departures(): HasMany
    {
        return $this->hasMany(Departure::class);
    }

    public function dayCount(): int
    {
        return count($this->days ?? []);
    }

    public function optionLabel(): string
    {
        return $this->name.' · '.self::dayLabel($this->dayCount());
    }

    /**
     * Days that are safe to render, or null when the stored payload is empty or malformed.
     *
     * @return list<array{title: string, description: string}>|null
     */
    public function renderableDays(): ?array
    {
        try {
            $days = self::normalizeDays($this->days);
        } catch (InvalidArgumentException) {
            return null;
        }
        $complete = array_filter($days, fn (array $day): bool => trim($day['title']) !== '');

        return $days !== [] && count($complete) === count($days) ? $days : null;
    }

    public static function dayLabel(int $days): string
    {
        return $days.' '.($days % 10 === 1 && $days % 100 !== 11 ? 'dan' : 'dana');
    }

    /**
     * Converts repeater state (possibly keyed by item UUIDs) into a sequential list with only known keys.
     *
     * @return list<array{title: string, description: string}>
     */
    public static function normalizeDays(mixed $days): array
    {
        if ($days === null) {
            return [];
        }
        if (! is_array($days)) {
            throw new InvalidArgumentException('Program days must be a list.');
        }

        return array_map(function (mixed $day): array {
            if (! is_array($day)) {
                throw new InvalidArgumentException('Each program day must be an object.');
            }
            $title = $day['title'] ?? '';
            $description = $day['description'] ?? '';
            if (! is_string($title) || ! is_string($description)) {
                throw new InvalidArgumentException('Program day title and description must be text.');
            }

            return ['title' => $title, 'description' => str_replace("\r\n", "\n", $description)];
        }, array_values($days));
    }
}
