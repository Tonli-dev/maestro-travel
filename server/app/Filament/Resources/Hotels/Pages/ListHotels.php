<?php

namespace App\Filament\Resources\Hotels\Pages;

use App\Filament\Resources\CatalogTable;
use App\Filament\Resources\Hotels\HotelResource;
use App\Models\Hotel;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHotels extends ListRecords
{
    protected static string $resource = HotelResource::class;

    public function getTabs(): array
    {
        return CatalogTable::statusTabs(Hotel::class);
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
