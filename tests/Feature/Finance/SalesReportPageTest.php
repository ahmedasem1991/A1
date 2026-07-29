<?php

namespace Tests\Feature\Finance;

use App\Filament\Pages\SalesReport;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SalesReportPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    public function test_sales_report_page_renders(): void
    {
        Livewire::test(SalesReport::class)->assertSuccessful();
    }

    public function test_sales_report_page_preset_switches_range(): void
    {
        Livewire::test(SalesReport::class)
            ->call('setPreset', 'yesterday')
            ->assertSet('from', now()->subDay()->startOfDay()->toDateTimeString())
            ->assertSet('to', now()->subDay()->endOfDay()->toDateTimeString());
    }

    public function test_the_visible_form_fields_reflect_the_mounted_default_range(): void
    {
        Livewire::test(SalesReport::class)
            ->assertFormSet([
                'from' => now()->startOfDay()->toDateTimeString(),
                'to' => now()->endOfDay()->toDateTimeString(),
            ]);
    }

    public function test_the_visible_form_fields_reflect_a_preset_after_its_clicked(): void
    {
        Livewire::test(SalesReport::class)
            ->call('setPreset', 'yesterday')
            ->assertFormSet([
                'from' => now()->subDay()->startOfDay()->toDateTimeString(),
                'to' => now()->subDay()->endOfDay()->toDateTimeString(),
            ]);
    }

    public function test_sales_report_page_can_filter_to_a_specific_time_window(): void
    {
        $customer = Customer::create(['name' => 'Time Window Customer']);

        $order = Order::create([
            'name' => $customer->name,
            'customer_id' => $customer->id,
            'subtotal' => 0,
            'discount' => 0,
            'total_price' => 0,
            'paid_amount' => 0,
            'remaining_amount' => 0,
            'status' => 'processing',
        ]);

        $product = Product::factory()->create(['name' => 'Morning Widget']);
        Inventory::create(['name' => 'Morning Widget Warehouse'])->products()->attach($product->id, ['stock_quantity' => 2]);

        $morningItem = OrderItem::create([
            'order_id' => $order->id,
            'category' => 'product',
            'product_id' => $product->id,
            'price' => 100,
            'quantity' => 1,
            'status' => 'creation',
        ]);
        $morningItem->created_at = now()->startOfDay()->addHours(9);
        $morningItem->save();

        $eveningItem = OrderItem::create([
            'order_id' => $order->id,
            'category' => 'product',
            'product_id' => $product->id,
            'price' => 100,
            'quantity' => 1,
            'status' => 'creation',
        ]);
        $eveningItem->created_at = now()->startOfDay()->addHours(18);
        $eveningItem->save();

        $component = Livewire::test(SalesReport::class)
            ->set('from', now()->startOfDay()->addHours(8)->toDateTimeString())
            ->set('to', now()->startOfDay()->addHours(12)->toDateTimeString());

        $summary = $component->instance()->getSummary();

        $this->assertEquals(1, $summary['total_items_sold']);
        $this->assertEquals(100, $summary['total_revenue']);
    }

    public function test_sales_report_page_shows_items_sold_today(): void
    {
        $customer = Customer::create(['name' => 'Sales Report Page Customer']);

        $order = Order::create([
            'name' => $customer->name,
            'customer_id' => $customer->id,
            'subtotal' => 0,
            'discount' => 0,
            'total_price' => 0,
            'paid_amount' => 0,
            'remaining_amount' => 0,
            'status' => 'processing',
        ]);

        $product = Product::factory()->create(['name' => 'Report Widget']);
        Inventory::create(['name' => 'Report Widget Warehouse'])->products()->attach($product->id, ['stock_quantity' => 1]);

        OrderItem::create([
            'order_id' => $order->id,
            'category' => 'product',
            'product_id' => $product->id,
            'price' => 100,
            'quantity' => 1,
            'status' => 'creation',
        ]);

        $component = Livewire::test(SalesReport::class);

        $summary = $component->instance()->getSummary();

        $this->assertEquals(1, $summary['total_items_sold']);
        $this->assertEquals(100, $summary['total_revenue']);
        $this->assertEquals('Report Widget', $summary['products']->first()['name']);
    }
}
