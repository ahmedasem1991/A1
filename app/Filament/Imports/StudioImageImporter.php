<?php

namespace App\Filament\Imports;

use App\Models\StudioImage;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class StudioImageImporter extends Importer
{
    protected static ?string $model = StudioImage::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('image_size')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('image_count')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('price')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'numeric', 'min:0']),
            ImportColumn::make('instant_price')
                ->numeric()
                ->rules(['numeric', 'min:0']),
            ImportColumn::make('soft_copy_price')
                ->numeric()
                ->rules(['numeric', 'min:0']),
            ImportColumn::make('name_price')
                ->numeric()
                ->rules(['numeric', 'min:0']),
        ];
    }

    public function resolveRecord(): ?StudioImage
    {
        return StudioImage::firstOrNew([
            'image_size' => $this->data['image_size'],
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your studio image import has completed and '.number_format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
