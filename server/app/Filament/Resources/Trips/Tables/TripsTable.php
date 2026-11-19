<?php

namespace App\Filament\Resources\Trips\Tables;

use App\Filament\Resources\CatalogTable;
use App\Models\Trip;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Compact trips list: star, title with destination, next date with count, status. Status tabs live on ListTrips.
 */
class TripsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => CatalogTable::withCurrentDateCount($query, 'departures'))
            ->columns([
                IconColumn::make('is_featured')->label('')->width('1%')
                    ->state(fn (Trip $record): bool => $record->is_featured && $record->canBeFeatured())
                    ->icon(fn (bool $state): Heroicon => $state ? Heroicon::Star : Heroicon::OutlinedStar)
                    ->color(fn (bool $state, Trip $record): string => match (true) {
                        ! $record->canBeFeatured() => 'gray',
                        $state => 'warning',
                        default => 'gray',
                    })
                    ->extraAttributes(fn (Trip $record): array => $record->canBeFeatured() ? [] : ['style' => 'opacity:.4', 'aria-disabled' => 'true'])
                    ->tooltip(fn (Trip $record): string => match (true) {
                        ! $record->canBeFeatured() => 'Samo objavljeno putovanje može biti izdvojeno.',
                        $record->is_featured => 'Ukloni iz naslovnog karusela.',
                        default => 'Dodaj u naslovni karusel. Prikazuje se kad putovanje ima aktualne termine.',
                    })
                    ->action(function (Trip $record): void {
                        if ($record->canBeFeatured()) {
                            $record->update(['is_featured' => ! $record->is_featured]);
                        }
                    })
                    ->disabledClick(fn (Trip $record): bool => ! $record->canBeFeatured())
                    ->alignCenter()->sortable(),
                TextColumn::make('title')->label('Putovanje')->searchable(['title', 'destination'])->sortable()
                    ->description(fn (Trip $record): string => $record->destination)->wrap(),
                CatalogTable::nextDate('departures', 'Sljedeći polazak'),
                CatalogTable::status(),
                CatalogTable::updatedAt(),
            ])->defaultSort('updated_at', 'desc')
            ->filters([
                TernaryFilter::make('is_featured')->label('Izdvojeno')->trueLabel('Samo izdvojena')->falseLabel('Bez izdvojenih'),
                TrashedFilter::make(),
            ])->recordActions(CatalogTable::recordActions());
    }
}
