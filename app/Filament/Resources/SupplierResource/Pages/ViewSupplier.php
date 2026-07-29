<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Resources\SupplierResource;
use App\Services\LedgerReportService;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Collection;

class ViewSupplier extends ViewRecord
{
    protected static string $resource = SupplierResource::class;

    protected static string $view = 'filament.resources.supplier-resource.pages.view-supplier';

    public function getLedger(): Collection
    {
        return app(LedgerReportService::class)->supplierLedger($this->record);
    }
}
