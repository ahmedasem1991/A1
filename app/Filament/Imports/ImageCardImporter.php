<?php

namespace App\Filament\Imports;

use App\Models\ImageCard;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class ImageCardImporter extends Importer
{
    protected static ?string $model = ImageCard::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('card_size')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('price')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'numeric', 'min:0']),
            ImportColumn::make('instant_price')
                ->numeric()
                ->rules(['numeric', 'min:0']),
        ];
    }

    public function resolveRecord(): ?ImageCard
    {
        return ImageCard::firstOrNew([
            'card_size' => $this->data['card_size'],
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your image card import has completed and '.number_format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
