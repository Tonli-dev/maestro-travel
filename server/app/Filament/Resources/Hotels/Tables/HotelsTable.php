<?php

namespace App\Filament\Resources\Hotels\Tables;

use App\Filament\Resources\CatalogTable;
use App\Models\Hotel;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Compact hotels list: name with destination, next dated rate with count, status. Status tabs live on ListHotels.
 */
class HotelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => CatalogTable::withCurrentDateCount($query, 'rates'))
            ->columns([
                TextColumn::make('name')->label('Hotel')->searchable(['name', 'destination'])->sortable()
                    ->description(fn (Hotel $record): string => $record->destination)->wrap(),
                CatalogTable::nextDate('rates', 'Sljedeći termin'),
                CatalogTable::status(),
                CatalogTable::updatedAt(),
            ])->defaultSort('updated_at', 'desc')
            ->filters([
                TrashedFilter::make(),
            ])->recordActions(CatalogTable::recordActions());
    }
}
