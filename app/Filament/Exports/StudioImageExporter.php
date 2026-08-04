<?php

namespace App\Filament\Exports;

use App\Models\StudioImage;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class StudioImageExporter extends Exporter
{
    protected static ?string $model = StudioImage::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('image_size'),
            ExportColumn::make('image_count'),
            ExportColumn::make('price'),
            ExportColumn::make('instant_price'),
            ExportColumn::make('soft_copy_price'),
            ExportColumn::make('name_price'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your studio image export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
