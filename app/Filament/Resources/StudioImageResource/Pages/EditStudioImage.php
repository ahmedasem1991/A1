<?php

namespace App\Filament\Resources\StudioImageResource\Pages;

use App\Filament\Resources\StudioImageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStudioImage extends EditRecord
{
    protected static string $resource = StudioImageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
