<?php

namespace Tests\Feature\Orders;

use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderItemStockDeductionTest extends TestCase
{
    use RefreshDatabase;

    protected Product $product;

    protected Inventory $inventory;

    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->product = Product::create([
            'name' => 'Wooden Photo Frame',
            'sku' => 'PRD-1001',
            'description' => 'A frame',
            'price' => 100,
            'base_price' => 70,
            'is_active' => true,
        ]);

        $this->inventory = Inventory::create(['name' => 'Main Warehouse']);
        $this->inventory->products()->attach($this->product->id, ['stock_quantity' => 10]);

        $customer = Customer::create(['name' => 'Stock Test Customer']);

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

    protected function stockFor(Product $product): int
    {
        return (int) $product->refresh()->inventories()->first()->pivot->stock_quantity;
    }

    public function test_creating_a_product_order_item_deducts_stock_immediately(): void
    {
        OrderItem::create([
            'order_id' => $this->order->id,
            'category' => 'product',
            'product_id' => $this->product->id,
            'quantity' => 3,
            'price' => 300,
            'status' => 'creation',
        ]);

        $this->assertSame(7, $this->stockFor($this->product));
    }

    public function test_creating_a_product_order_item_exceeding_stock_throws(): void
    {
        $this->expectException(InsufficientStockException::class);

        OrderItem::create([
            'order_id' => $this->order->id,
            'category' => 'product',
            'product_id' => $this->product->id,
            'quantity' => 999,
            'price' => 300,
            'status' => 'creation',
        ]);
    }

    public function test_increasing_quantity_deducts_the_extra_amount(): void
    {
        $item = OrderItem::create([
            'order_id' => $this->order->id,
            'category' => 'product',
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 200,
            'status' => 'creation',
        ]);

        $this->assertSame(8, $this->stockFor($this->product));

        $item->update(['quantity' => 5]);

        $this->assertSame(5, $this->stockFor($this->product));
    }

    public function test_decreasing_quantity_restores_the_difference(): void
    {
        $item = OrderItem::create([
            'order_id' => $this->order->id,
            'category' => 'product',
            'product_id' => $this->product->id,
            'quantity' => 5,
            'price' => 500,
            'status' => 'creation',
        ]);

        $this->assertSame(5, $this->stockFor($this->product));

        $item->update(['quantity' => 2]);

        $this->assertSame(8, $this->stockFor($this->product));
    }

    public function test_deleting_a_product_order_item_restores_stock(): void
    {
        $item = OrderItem::create([
            'order_id' => $this->order->id,
            'category' => 'product',
            'product_id' => $this->product->id,
            'quantity' => 4,
            'price' => 400,
            'status' => 'creation',
        ]);

        $this->assertSame(6, $this->stockFor($this->product));

        $item->delete();

        $this->assertSame(10, $this->stockFor($this->product));
    }

    public function test_non_product_order_items_do_not_touch_stock(): void
    {
        OrderItem::create([
            'order_id' => $this->order->id,
            'category' => 'studio_image',
            'quantity' => 1,
            'price' => 100,
            'status' => 'creation',
        ]);

        $this->assertSame(10, $this->stockFor($this->product));
    }
}
