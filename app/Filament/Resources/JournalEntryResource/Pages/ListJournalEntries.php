<?php

namespace App\Filament\Resources\JournalEntryResource\Pages;

use App\Filament\Resources\JournalEntryResource;
use Filament\Resources\Pages\ListRecords;

class ListJournalEntries extends ListRecords
{
    protected static string $resource = JournalEntryResource::class;
}
