<?php

namespace App\Filament\Resources\Hotels\Pages;

use App\Filament\Resources\Hotels\HotelResource;
use App\Support\CatalogAdminActions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateHotel extends CreateRecord
{
    protected static string $resource = HotelResource::class;

    public static bool $formActionsAreSticky = true;

    protected function getRedirectUrl(): string
    {
        return CatalogAdminActions::editUrl($this->getRecord());
    }

    protected function getCreatedNotification(): ?Notification
    {
        return CatalogAdminActions::notification($this->getRecord());
    }
}
