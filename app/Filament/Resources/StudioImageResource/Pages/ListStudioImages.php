<?php

namespace App\Filament\Resources\StudioImageResource\Pages;

use App\Filament\Resources\StudioImageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStudioImages extends ListRecords
{
    protected static string $resource = StudioImageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
