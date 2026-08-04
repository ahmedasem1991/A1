<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderItemResource;
use App\Filament\Resources\OrderResource;
use App\Models\OrderItem;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $existingItemIds = $this->record->orderItems()->pluck('id')->toArray();
        $submittedItemIds = collect($data['orderItems'] ?? [])
            ->filter(fn ($item) => isset($item['id']))
            ->pluck('id')
            ->toArray();

        $removedIds = array_diff($existingItemIds, $submittedItemIds);

        foreach ($removedIds as $id) {
            $item = OrderItem::find($id);

            if ($item && $item->status === 'creation') {
                $item->delete();
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->orderItems()
            ->where('category', 'product')
            ->where('status', 'creation')
            ->update(['status' => 'completed']);

        $record = $this->record->refresh();

        $subtotal = $record->orderItems()->sum('price');
        $total = max(0, $subtotal - ($record->discount ?? 0));
        $remaining = max(0, $total - ($record->paid_amount ?? 0));

        $record->update([
            'subtotal' => $subtotal,
            'total_price' => $total,
            'remaining_amount' => $remaining,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return OrderItemResource::getUrl('index', [
            'tableFilters' => [
                'order_id' => ['value' => $this->record->id],
            ],
        ]);
    }
}
