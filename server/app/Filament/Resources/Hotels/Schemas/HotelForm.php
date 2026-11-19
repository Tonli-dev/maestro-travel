<?php

namespace App\Filament\Resources\Hotels\Schemas;

use App\Filament\Resources\CatalogFields;
use App\Support\CatalogPresentation;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

/**
 * Hotel editor, grouped as basics, packages with dated rates, description, photos.
 */
class HotelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Osnovni podaci')->description('Naziv, objava i oznake hotela.')->schema([
                TextInput::make('name')->label('Naziv')->required()->maxLength(255),
                CatalogFields::destination(),
                TextInput::make('address')->label('Adresa')->maxLength(255),
                TextInput::make('stars')->label('Zvjezdice')->integer()->minValue(1)->maxValue(5),
                CatalogFields::status(),
                CatalogFields::labels('categories', 'category'),
                CatalogFields::labels('regions', 'region'),
                CatalogFields::labels('amenities', 'amenity'),
            ])->columns(2)->columnSpanFull(),
            Section::make('Paketi i termini')
                ->description('Paket opisuje smještaj i uslugu; termini u paketu imaju datume i cijene. Za sličan paket ili termin koristite Dupliciraj.')
                ->schema([
                    Repeater::make('packages')->hiddenLabel()->relationship()->schema([
                        TextInput::make('name')->label('Naziv paketa')->required()->maxLength(255),
                        TextInput::make('room_type')->label('Tip sobe')->maxLength(255),
                        TextInput::make('board')->label('Usluga prehrane')->maxLength(255),
                        TextInput::make('nights')->label('Broj noćenja')->integer()->minValue(1)->maxValue(365),
                        Select::make('price_basis')->label('Cijena se odnosi na')->options([
                            'person_package' => 'Osobu / paket', 'room_package' => 'Sobu / paket',
                            'person_night' => 'Osobu / noć', 'room_night' => 'Sobu / noć',
                        ])->default('person_package')->required(),
                        Textarea::make('notes')->label('Uvjeti i doplate')->maxLength(10000)->columnSpanFull(),
                        Repeater::make('rates')->label('Termini i cijene')
                            ->relationship(modifyQueryUsing: fn (Builder $query): Builder => $query->orderBy('starts_at')->orderBy('id'))
                            ->schema(CatalogFields::rateFields())->columns(3)
                            ->itemLabel(fn (array $state): string => CatalogFields::rateLabel($state))
                            ->defaultItems(0)->addActionLabel('Dodaj termin/cijenu')
                            ->reorderable(false)->cloneable()->collapsible()->collapsed(CatalogFields::collapseSavedItems())
                            ->columnSpanFull(),
                    ])->columns(2)
                        ->itemLabel(fn (array $state): string => self::packageLabel($state))
                        ->defaultItems(0)->addActionLabel('Dodaj paket')
                        ->reorderable(false)->cloneable()->collapsible()->collapsed(CatalogFields::collapseSavedItems()),
                ])->columnSpanFull(),
            Section::make('Opis')->schema([
                Textarea::make('description')->label('Opis')->rows(5)->maxLength(20000)->columnSpanFull(),
            ])->collapsible()->columnSpanFull(),
            Section::make('Fotografije')->schema([
                CatalogFields::images('hotels'),
            ])->collapsible()->columnSpanFull(),
        ]);
    }

    /** @param array<string, mixed> $state */
    private static function packageLabel(array $state): string
    {
        $name = is_string($state['name'] ?? null) && filled($state['name']) ? $state['name'] : 'Novi paket';
        $nights = is_numeric($state['nights'] ?? null) ? (int) $state['nights'] : null;

        return implode(' · ', array_filter([
            $name,
            $nights ? $nights.' '.($nights % 10 === 1 && $nights % 100 !== 11 ? 'noćenje' : 'noćenja') : null,
            CatalogPresentation::BASES[$state['price_basis'] ?? ''] ?? null,
        ]));
    }
}
