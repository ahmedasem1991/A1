<?php

namespace App\Services;

use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SalesReportService
{
    /**
     * Summarize items sold between the given instants (inclusive), grouped by
     * item within each category. Counts every OrderItem regardless of its
     * production workflow status. Callers control the precision of $from/$to
     * (e.g. whole days, or a specific time-of-day window).
     *
     * @return array{
     *     products: Collection<int, array{name: string, times_sold: int, total_quantity: int, total_revenue: float}>,
     *     studio_images: Collection<int, array{name: string, times_sold: int, total_quantity: int, total_revenue: float}>,
     *     image_cards: Collection<int, array{name: string, times_sold: int, total_quantity: int, total_revenue: float}>,
     *     total_items_sold: int,
     *     total_revenue: float,
     *     products_revenue: float,
     *     studio_images_revenue: float,
     *     image_cards_revenue: float,
     * }
     */
    public function summarizeRange(Carbon $from, Carbon $to): array
    {
        $products = $this->summarizeCategory('product', 'product_id', 'product', 'name', $from, $to);
        $studioImages = $this->summarizeCategory('studio_image', 'studio_image_id', 'studioImage', 'image_size', $from, $to);
        $imageCards = $this->summarizeCategory('image_card', 'image_card_id', 'imageCard', 'card_size', $from, $to);

        $allGroups = $products->concat($studioImages)->concat($imageCards);

        return [
            'products' => $products,
            'studio_images' => $studioImages,
            'image_cards' => $imageCards,
            'total_items_sold' => (int) $allGroups->sum('times_sold'),
            'total_revenue' => (float) $allGroups->sum('total_revenue'),
            'products_revenue' => (float) $products->sum('total_revenue'),
            'studio_images_revenue' => (float) $studioImages->sum('total_revenue'),
            'image_cards_revenue' => (float) $imageCards->sum('total_revenue'),
        ];
    }

    /**
     * @return Collection<int, array{name: string, times_sold: int, total_quantity: int, total_revenue: float}>
     */
    private function summarizeCategory(
        string $category,
        string $foreignKey,
        string $relation,
        string $nameField,
        Carbon $from,
        Carbon $to,
    ): Collection {
        return OrderItem::query()
            ->where('category', $category)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw("{$foreignKey}, count(*) as times_sold, sum(quantity) as total_quantity, sum(price) as total_revenue")
            ->groupBy($foreignKey)
            ->with($relation)
            ->get()
            ->filter(fn (OrderItem $group) => $group->{$relation} !== null)
            ->map(fn (OrderItem $group) => [
                'name' => $group->{$relation}->{$nameField},
                'times_sold' => (int) $group->times_sold,
                'total_quantity' => (int) $group->total_quantity,
                'total_revenue' => (float) $group->total_revenue,
            ])
            ->sortByDesc('times_sold')
            ->values();
    }
}
