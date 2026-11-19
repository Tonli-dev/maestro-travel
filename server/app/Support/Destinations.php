<?php

namespace App\Support;

use App\Models\Hotel;
use App\Models\Trip;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Keeps destination spelling consistent across trips and hotels.
 *
 * Destinations are free text; this suggests existing names, reuses the established spelling for
 * case/diacritic/spacing variants ("istanbul", "Bec" → "Istanbul", "Beč"), and flags likely typos
 * ("Istambul", or "Istanbul" when "Istanbul, Turska" exists) without silently changing them.
 */
class Destinations
{
    /** @var Collection<string, string>|null normalized key => preferred spelling */
    private ?Collection $known = null;

    /** @return list<string> */
    public function all(): array
    {
        return $this->known()->values()->sort(fn (string $a, string $b): int => strcmp($this->key($a), $this->key($b)))->values()->all();
    }

    /** Trims/collapses spacing and returns the established spelling when only case, accents or spacing differ. */
    public function canonical(?string $destination): ?string
    {
        if ($destination === null) {
            return null;
        }
        $clean = $this->clean($destination);

        return $clean === '' ? $clean : ($this->known()->get($this->key($clean)) ?? $clean);
    }

    /** An existing destination that is probably the intended spelling of a new value, or null. */
    public function similar(?string $destination): ?string
    {
        if (! is_string($destination) || $this->clean($destination) === '') {
            return null;
        }
        $key = $this->key($destination);
        if ($this->known()->has($key)) {
            return null;
        }
        $limit = mb_strlen($key) <= 5 ? 1 : 2;
        $place = fn (string $value): string => trim(explode(',', $value, 2)[0]);

        return $this->known()
            ->map(fn (string $name, string $knownKey): int => min(
                levenshtein($key, $knownKey),
                $place($key) === $key && $place($knownKey) !== $knownKey ? levenshtein($key, $place($knownKey)) : PHP_INT_MAX,
            ))
            ->filter(fn (int $distance): bool => $distance <= $limit)
            ->sort()->keys()
            ->map(fn (string $knownKey): string => $this->known()->get($knownKey))
            ->first();
    }

    public function key(string $destination): string
    {
        return Str::ascii(mb_strtolower($this->clean($destination)));
    }

    private function clean(string $destination): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $destination));
    }

    /** @return Collection<string, string> */
    private function known(): Collection
    {
        return $this->known ??= Trip::query()->toBase()->select('destination')
            ->unionAll(Hotel::query()->toBase()->select('destination'))
            ->pluck('destination')
            ->filter(fn (mixed $name): bool => is_string($name) && $this->clean($name) !== '')
            ->map(fn (string $name): string => $this->clean($name))
            ->groupBy(fn (string $name): string => $this->key($name))
            ->map(fn (Collection $spellings): string => $spellings->countBy()->sortDesc()->keys()->first());
    }
}
