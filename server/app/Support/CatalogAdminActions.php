<?php

namespace App\Support;

use App\Filament\Resources\Hotels\HotelResource;
use App\Filament\Resources\Trips\TripResource;
use App\Models\Hotel;
use App\Models\Trip;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class CatalogAdminActions
{
    /** @return array<Action> */
    public static function links(): array
    {
        return [
            Action::make('previewSaved')->label('Pregled spremljene ponude')
                ->tooltip('Prikazuje posljednje spremljene podatke. Ne sprema promjene iz obrasca.')
                ->url(fn (Trip|Hotel $record): string => self::previewUrl($record))->openUrlInNewTab()
                ->visible(fn (Trip|Hotel $record): bool => ! $record->trashed()),
            Action::make('openPublic')->label('Otvori javnu ponudu')
                ->url(fn (Trip|Hotel $record): string => self::publicUrl($record))->openUrlInNewTab()
                ->visible(fn (Trip|Hotel $record): bool => ! $record->trashed() && $record->status === 'published'),
        ];
    }

    public static function previewUrl(Trip|Hotel $record): string
    {
        return route($record instanceof Hotel ? 'admin.preview.hotels' : 'admin.preview.trips', $record->id);
    }

    public static function publicUrl(Trip|Hotel $record): string
    {
        return route($record instanceof Hotel ? 'hotels.show' : 'trips.show', $record->slug);
    }

    public static function editUrl(Trip|Hotel $record): string
    {
        return ($record instanceof Hotel ? HotelResource::class : TripResource::class)::getUrl('edit', ['record' => $record]);
    }

    public static function notification(Trip|Hotel $record): Notification
    {
        $record->refresh();
        $actions = [
            Action::make('previewSaved')->label('Pregled spremljene ponude')->url(self::previewUrl($record))->openUrlInNewTab(),
        ];
        if ($record->status === 'published') {
            $actions[] = Action::make('openPublic')->label('Otvori javnu ponudu')->url(self::publicUrl($record))->openUrlInNewTab();
        }

        return Notification::make()->success()->title('Ponuda je spremljena')
            ->body('Pregled prikazuje posljednje spremljene podatke. Spremanje objavljene ponude odmah mijenja javni prikaz.')
            ->actions($actions)->persistent();
    }
}
