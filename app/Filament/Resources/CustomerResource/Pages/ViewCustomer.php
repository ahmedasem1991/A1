<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Services\LedgerReportService;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Collection;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected static string $view = 'filament.resources.customer-resource.pages.view-customer';

    public function getLedger(): Collection
    {
        return app(LedgerReportService::class)->customerLedger($this->record);
    }
}
