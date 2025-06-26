<?php

namespace App\Filament\Resources\ImageCardResource\Pages;

use App\Filament\Resources\ImageCardResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditImageCard extends EditRecord
{
    protected static string $resource = ImageCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
