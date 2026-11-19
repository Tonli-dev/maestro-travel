<?php

namespace App\Filament\Resources\Labels\Pages;

use App\Filament\Resources\Labels\LabelResource;
use App\Models\Label;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLabels extends ManageRecords
{
    protected static string $resource = LabelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Dodaj oznaku')->using(fn (array $data): Label => Label::resolveName($data['kind'], $data['name'], $data['featured'] ?? false)),
        ];
    }
}
