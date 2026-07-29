<?php

namespace Tests\Feature\Finance;

use App\Models\Customer;
use App\Models\ImageCard;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StudioImage;
use App\Services\SalesReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $customer = Customer::create(['name' => 'Sales Report Customer']);

        $this->order = Order::create([
            'name' => $customer->name,
            'customer_id' => $customer->id,
            'subtotal' => 0,
            'discount' => 0,
            'total_price' => 0,
            'paid_amount' => 0,
            'remaining_amount' => 0,
            'status' => 'processing',
        ]);
    }

    private function makeItem(array $attributes): OrderItem
    {
        $merged = array_merge([
            'order_id' => $this->order->id,
            'quantity' => 1,
            'status' => 'creation',
        ], $attributes);

        if (($merged['category'] ?? null) === 'product' && isset($merged['product_id'])) {
            $this->ensureStock($merged['product_id'], $merged['quantity']);
        }

        return OrderItem::create($merged);
    }

    private function ensureStock(int $productId, int $quantity): void
    {
        $inventory = Inventory::firstOrCreate(['name' => 'Sales Report Test Warehouse']);
        $existing = $inventory->products()->where('product_id', $productId)->first();

        if ($existing) {
            $inventory->products()->updateExistingPivot($productId, [
                'stock_quantity' => $existing->pivot->stock_quantity + $quantity,
            ]);

            return;
        }

        $inventory->products()->attach($productId, ['stock_quantity' => $quantity]);
    }

    public function test_it_groups_products_by_item_and_counts_times_sold(): void
    {
        $productA = Product::factory()->create(['name' => 'Product A']);
        $productB = Product::factory()->create(['name' => 'Product B']);

        $this->makeItem(['category' => 'product', 'product_id' => $productA->id, 'price' => 100, 'quantity' => 2]);
        $this->makeItem(['category' => 'product', 'product_id' => $productA->id, 'price' => 100, 'quantity' => 1]);
        $this->makeItem(['category' => 'product', 'product_id' => $productB->id, 'price' => 50, 'quantity' => 1]);

        $result = app(SalesReportService::class)->summarizeRange(now()->startOfDay(), now()->endOfDay());

        $products = $result['products']->keyBy('name');

        $this->assertEquals(2, $products['Product A']['times_sold']);
        $this->assertEquals(3, $products['Product A']['total_quantity']);
        $this->assertEquals(200, $products['Product A']['total_revenue']);

        $this->assertEquals(1, $products['Product B']['times_sold']);
        $this->assertEquals(50, $products['Product B']['total_revenue']);
    }

    public function test_it_groups_studio_images_and_image_cards_separately(): void
    {
        $studioImage = StudioImage::factory()->create(['image_size' => '8x10']);
        $imageCard = ImageCard::factory()->create(['card_size' => 'Postcard']);

        $this->makeItem(['category' => 'studio_image', 'studio_image_id' => $studioImage->id, 'price' => 150, 'quantity' => 1]);
        $this->makeItem(['category' => 'studio_image', 'studio_image_id' => $studioImage->id, 'price' => 150, 'quantity' => 1]);
        $this->makeItem(['category' => 'image_card', 'image_card_id' => $imageCard->id, 'price' => 30, 'quantity' => 2]);

        $result = app(SalesReportService::class)->summarizeRange(now()->startOfDay(), now()->endOfDay());

        $this->assertEquals(1, $result['studio_images']->count());
        $this->assertEquals('8x10', $result['studio_images']->first()['name']);
        $this->assertEquals(2, $result['studio_images']->first()['times_sold']);

        $this->assertEquals(1, $result['image_cards']->count());
        $this->assertEquals('Postcard', $result['image_cards']->first()['name']);
        $this->assertEquals(2, $result['image_cards']->first()['total_quantity']);

        $this->assertEquals(0, $result['products']->count());
    }

    public function test_it_counts_items_regardless_of_workflow_status(): void
    {
        $product = Product::factory()->create();

        $this->makeItem(['category' => 'product', 'product_id' => $product->id, 'price' => 100, 'status' => 'creation']);
        $this->makeItem(['category' => 'product', 'product_id' => $product->id, 'price' => 100, 'status' => 'printing']);
        $this->makeItem(['category' => 'product', 'product_id' => $product->id, 'price' => 100, 'status' => 'completed']);

        $result = app(SalesReportService::class)->summarizeRange(now()->startOfDay(), now()->endOfDay());

        $this->assertEquals(3, $result['products']->first()['times_sold']);
    }

    public function test_it_excludes_items_outside_the_date_range(): void
    {
        $product = Product::factory()->create();

        $this->makeItem(['category' => 'product', 'product_id' => $product->id, 'price' => 100]);

        $outOfRange = $this->makeItem(['category' => 'product', 'product_id' => $product->id, 'price' => 100]);
        $outOfRange->created_at = now()->subDays(10);
        $outOfRange->save();

        $result = app(SalesReportService::class)->summarizeRange(now()->startOfDay(), now()->endOfDay());

        $this->assertEquals(1, $result['products']->first()['times_sold']);
        $this->assertEquals(1, $result['total_items_sold']);
    }

    public function test_total_items_sold_and_total_revenue_sum_across_all_categories(): void
    {
        $product = Product::factory()->create();
        $studioImage = StudioImage::factory()->create();
        $imageCard = ImageCard::factory()->create();

        $this->makeItem(['category' => 'product', 'product_id' => $product->id, 'price' => 100]);
        $this->makeItem(['category' => 'studio_image', 'studio_image_id' => $studioImage->id, 'price' => 75]);
        $this->makeItem(['category' => 'image_card', 'image_card_id' => $imageCard->id, 'price' => 25]);

        $result = app(SalesReportService::class)->summarizeRange(now()->startOfDay(), now()->endOfDay());

        $this->assertEquals(3, $result['total_items_sold']);
        $this->assertEquals(200, $result['total_revenue']);
    }

    public function test_a_date_range_spanning_multiple_days_includes_all_days_inclusive(): void
    {
        $product = Product::factory()->create();

        $this->makeItem(['category' => 'product', 'product_id' => $product->id, 'price' => 100]);

        $threeDaysAgo = $this->makeItem(['category' => 'product', 'product_id' => $product->id, 'price' => 100]);
        $threeDaysAgo->created_at = now()->subDays(3);
        $threeDaysAgo->save();

        $result = app(SalesReportService::class)->summarizeRange(now()->subDays(5)->startOfDay(), now()->endOfDay());

        $this->assertEquals(2, $result['products']->first()['times_sold']);
    }

    public function test_it_respects_a_sub_day_time_window(): void
    {
        $product = Product::factory()->create();

        $morningItem = $this->makeItem(['category' => 'product', 'product_id' => $product->id, 'price' => 100]);
        $morningItem->created_at = now()->startOfDay()->addHours(9);
        $morningItem->save();

        $eveningItem = $this->makeItem(['category' => 'product', 'product_id' => $product->id, 'price' => 100]);
        $eveningItem->created_at = now()->startOfDay()->addHours(18);
        $eveningItem->save();

        $result = app(SalesReportService::class)->summarizeRange(
            now()->startOfDay()->addHours(8),
            now()->startOfDay()->addHours(12),
        );

        $this->assertEquals(1, $result['total_items_sold']);
        $this->assertEquals(100, $result['total_revenue']);
    }
}
