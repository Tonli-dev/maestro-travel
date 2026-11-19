<?php

namespace App\Filament\Resources\Trips\Schemas;

use App\Filament\Resources\CatalogFields;
use App\Models\Trip;
use App\Models\TripProgram;
use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;
use Throwable;

/**
 * Program variants with ordered days, and the per-departure program selector.
 *
 * Departures can only reference saved programs of the same trip. New programs get IDs on save,
 * so the selector offers them after the next save instead of persisting temporary repeater keys.
 */
class TripProgramFields
{
    public static function programs(): Repeater
    {
        return Repeater::make('programs')->label('Programi putovanja')->relationship()
            ->helperText('Svaki program je zasebna verzija itinerara, npr. 3 dana i 5 dana. Nakon spremanja program odaberite u terminima. Izmjena programa mijenja prikaz svih termina koji ga koriste.')
            ->schema([
                TextInput::make('name')->label('Naziv programa')->placeholder('npr. Istanbul — 3 dana')
                    ->required()->maxLength(120)->rules(['string'])->columnSpanFull(),
                Repeater::make('days')->label('Dani ovog programa')
                    ->extraAttributes(['class' => 'maestro-days'])
                    ->schema([
                        TextInput::make('title')->label('Naslov dana')->required()->maxLength(160)->rules(['string']),
                        Textarea::make('description')->label('Opis dana')->rows(4)->maxLength(5000)->rules(['nullable', 'string'])
                            ->helperText('Prazan red odvaja odlomke.'),
                    ])
                    ->itemLabel(fn (?int $index, array $state): string => 'Dan '.($index + 1)
                        .(is_string($state['title'] ?? null) && filled($state['title']) ? ' · '.$state['title'] : ''))
                    ->minItems(1)->maxItems(TripProgram::MAX_DAYS)->required()
                    ->defaultItems(1)->addActionLabel('Dodaj dan u ovaj program')
                    ->reorderableWithButtons()->collapsible()->columnSpanFull()
                    ->validationMessages(['min' => 'Program mora imati barem jedan dan.']),
            ])
            ->extraAttributes(['class' => 'maestro-programs'])
            ->itemLabel(fn (?int $index, array $state): string => 'Program '.($index + 1).' · '
                .(is_string($state['name'] ?? null) && filled($state['name']) ? $state['name'] : 'novi program, upišite naziv')
                .' · '.TripProgram::dayLabel(count(is_array($state['days'] ?? null) ? $state['days'] : [])))
            ->defaultItems(0)->addActionLabel('Dodaj program')
            ->reorderable(false)->cloneable()->collapsible()->collapsed(CatalogFields::collapseSavedItems())->columnSpanFull()
            ->rules([fn (Get $get, Component $livewire): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $livewire): void {
                $trip = self::trip($livewire);
                if ($trip === null) {
                    return;
                }
                $kept = self::savedProgramIds(is_array($value) ? $value : []);
                $removed = $trip->programs()->pluck('name', 'id')->except($kept);
                $assigned = collect(is_array($get('departures')) ? $get('departures') : [])
                    ->map(fn (mixed $departure): mixed => is_array($departure) ? ($departure['trip_program_id'] ?? null) : null)
                    ->filter(fn (mixed $id): bool => filled($id))->map(fn (mixed $id): int => (int) $id);
                foreach ($removed as $id => $name) {
                    $uses = $assigned->filter(fn (int $assignedId): bool => $assignedId === $id)->count();
                    if ($uses > 0) {
                        $fail('Program „'.$name.'“ koristi '.$uses.' '.($uses === 1 ? 'termin' : 'termina').'. Prvo u terminima odaberite drugi program ili uklonite odabir, pa spremite.');
                    }
                }
            }]);
    }

    public static function departureProgram(): Select
    {
        return Select::make('trip_program_id')->label('Program putovanja')
            ->placeholder('Bez programa (prikazuje se prijelazni tekst)')
            ->options(fn (Component $livewire): array => self::trip($livewire)?->programs
                ->mapWithKeys(fn (TripProgram $program): array => [$program->id => $program->optionLabel()])->all() ?? [])
            ->disabled(fn (Component $livewire): bool => self::trip($livewire) === null)
            ->live()
            ->helperText(fn (Get $get, Component $livewire): string => self::departureHelp($get, self::trip($livewire)))
            ->rules([fn (Component $livewire): Closure => function (string $attribute, mixed $value, Closure $fail) use ($livewire): void {
                if (blank($value)) {
                    return;
                }
                $trip = self::trip($livewire);
                $belongs = $trip !== null && (is_int($value) || (is_string($value) && ctype_digit($value)))
                    && TripProgram::query()->whereKey((int) $value)->where('trip_id', $trip->id)->exists();
                if (! $belongs) {
                    $fail('Odabrani program ne postoji ili ne pripada ovom putovanju.');
                }
            }])
            ->columnSpan(2);
    }

    public static function trip(Component $livewire): ?Trip
    {
        $record = $livewire instanceof EditRecord ? $livewire->getRecord() : null;

        return $record instanceof Trip && $record->exists ? $record : null;
    }

    /**
     * IDs of already-saved programs kept in the submitted repeater state (keys "record-{id}").
     *
     * @param  array<array-key, mixed>  $state
     * @return list<int>
     */
    public static function savedProgramIds(array $state): array
    {
        return collect(array_keys($state))
            ->map(fn (int|string $key): ?int => preg_match('/^record-(\d+)$/', (string) $key, $match) ? (int) $match[1] : null)
            ->filter()->values()->all();
    }

    private static function departureHelp(Get $get, ?Trip $trip): string
    {
        if ($trip === null) {
            return 'Spremite putovanje i njegove programe, zatim ovdje odaberite program za termin.';
        }
        $general = 'Više termina može koristiti isti program. Novi programi pojavljuju se ovdje nakon spremanja.';
        $selectedId = $get('trip_program_id');
        /** @var Collection<int, TripProgram> $programs */
        $programs = $trip->programs;
        $program = filled($selectedId) ? $programs->firstWhere('id', (int) $selectedId) : null;
        $duration = self::durationInDays($get('starts_at'), $get('ends_at'));
        if ($program === null || $duration === null || $program->dayCount() === $duration) {
            return $general;
        }

        return 'Upozorenje: program ima '.TripProgram::dayLabel($program->dayCount()).', a termin prema datumima traje '
            .TripProgram::dayLabel($duration).'. Datumi i program se ne mijenjaju automatski. '.$general;
    }

    private static function durationInDays(mixed $startsAt, mixed $endsAt): ?int
    {
        if (! is_string($startsAt) || ! is_string($endsAt) || blank($startsAt) || blank($endsAt)) {
            return null;
        }
        try {
            $start = Carbon::parse($startsAt)->startOfDay();
            $end = Carbon::parse($endsAt)->startOfDay();
        } catch (Throwable) {
            return null;
        }

        return $end->lessThan($start) ? null : (int) $start->diffInDays($end) + 1;
    }
}
