<?php

namespace App\Filament\Resources\Trips\Pages;

use App\Filament\Resources\Trips\TripResource;
use App\Support\CatalogAdminActions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateTrip extends CreateRecord
{
    protected static string $resource = TripResource::class;

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
