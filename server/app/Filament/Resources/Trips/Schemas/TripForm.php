<?php

namespace App\Filament\Resources\Trips\Schemas;

use App\Filament\Resources\CatalogFields;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/**
 * Trip editor, grouped in the order employees usually work: basics, dates, programs, texts, photos.
 * Departures are saved before programs so one save can move departures off a removed program.
 */
class TripForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Osnovni podaci')->description('Naziv, objava i oznake putovanja.')->schema([
                TextInput::make('title')->label('Naziv')->required()->maxLength(255),
                CatalogFields::destination(),
                Select::make('transport')->label('Prijevoz')->options([
                    'bus' => 'Autobus', 'plane' => 'Avion', 'own' => 'Vlastiti prijevoz',
                ]),
                CatalogFields::status()->helperText('Spremanje sa statusom Objavljeno odmah mijenja javnu ponudu. Pregled prikazuje samo posljednje spremljene podatke. Zvjezdicom u popisu putovanja objavljeno putovanje dodajete u naslovni karusel.'),
                CatalogFields::labels('categories', 'category'),
                CatalogFields::labels('regions', 'region'),
            ])->columns(2)->columnSpanFull(),
            Section::make('Termini')
                ->description('Svaki termin ima datume, cijenu i dostupnost. Za sličan polazak koristite Dupliciraj pa promijenite datume.')
                ->schema([
                    Repeater::make('departures')->hiddenLabel()
                        ->relationship(modifyQueryUsing: fn (Builder $query): Builder => $query->orderBy('starts_at')->orderBy('id'))
                        ->schema([
                            ...array_map(
                                fn (Field $field): Field => in_array($field->getName(), ['starts_at', 'ends_at'], true) ? $field->live() : $field,
                                CatalogFields::rateFields(),
                            ),
                            TextInput::make('departure_place')->label('Polazište')->maxLength(255),
                            TripProgramFields::departureProgram(),
                        ])->columns(3)
                        ->itemLabel(fn (array $state, Component $livewire): string => self::departureLabel($state, $livewire))
                        ->defaultItems(0)->addActionLabel('Dodaj termin')
                        ->reorderable(false)->cloneable()->collapsible()->collapsed(CatalogFields::collapseSavedItems()),
                ])->columnSpanFull(),
            Section::make('Programi putovanja')
                ->description('Program po danima. Jedno putovanje može imati više verzija, npr. 3 i 5 dana.')
                ->schema([
                    TripProgramFields::programs(),
                ])->collapsible()->columnSpanFull(),
            Section::make('Opis i uključeno')->schema([
                Textarea::make('description')->label('Opis')->rows(3)->maxLength(10000)->columnSpanFull(),
                Textarea::make('included')->label('Uključeno u cijenu')->maxLength(10000),
                Textarea::make('excluded')->label('Doplate i dodatni troškovi')->maxLength(10000),
                Textarea::make('program')->label('Prijelazni program (slobodan tekst)')->rows(6)->maxLength(30000)->columnSpanFull()
                    ->helperText('Prikazuje se za termine bez odabranog programa i kada nema aktualnih termina. Za program po danima koristite odjeljak Programi putovanja.'),
            ])->columns(2)->collapsible()->columnSpanFull(),
            Section::make('Fotografije')->schema([
                CatalogFields::images('trips'),
            ])->collapsible()->columnSpanFull(),
        ]);
    }

    /** @param array<string, mixed> $state */
    private static function departureLabel(array $state, Component $livewire): string
    {
        $label = CatalogFields::rateLabel($state);
        $programId = $state['trip_program_id'] ?? null;
        $program = filled($programId) ? TripProgramFields::trip($livewire)?->programs->firstWhere('id', (int) $programId) : null;
        $place = $state['departure_place'] ?? null;

        return $label
            .(is_string($place) && filled($place) ? ' · '.$place : '')
            .($program ? ' · '.$program->name : '');
    }
}
