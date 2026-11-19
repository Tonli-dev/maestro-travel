<?php

namespace App\Filament\Resources\Trips\Pages;

use App\Filament\Resources\CatalogTable;
use App\Filament\Resources\Trips\TripResource;
use App\Models\Trip;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTrips extends ListRecords
{
    protected static string $resource = TripResource::class;

    public function getTabs(): array
    {
        return CatalogTable::statusTabs(Trip::class);
    }

    public function getDefaultActiveTab(): string
    {
        return 'aktivna';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
