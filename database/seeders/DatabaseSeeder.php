<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ImageCard;
use App\Models\Inventory;
use App\Models\InventoryProduct;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\StudioImage;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🌱 Starting comprehensive database seeding...');

        // 1. Create Users with different roles
        $this->command->info('👥 Creating users...');

        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        $photographer = User::factory()->create([
            'name' => 'Photographer John',
            'email' => 'photographer@example.com',
            'password' => Hash::make('password'),
        ]);

        $editor = User::factory()->create([
            'name' => 'Editor Sarah',
            'email' => 'editor@example.com',
            'password' => Hash::make('password'),
        ]);

        $cashier = User::factory()->create([
            'name' => 'Cashier Mike',
            'email' => 'cashier@example.com',
            'password' => Hash::make('password'),
        ]);

        $users = User::factory(5)->create();
        $allUsers = collect([$admin, $photographer, $editor, $cashier])->merge($users);

        $this->command->info("✓ Created {$allUsers->count()} users");

        // 2. Create Product Categories
        $this->command->info('📦 Creating categories...');
        $categories = Category::factory(5)->create();
        $this->command->info("✓ Created {$categories->count()} categories");

        // 3. Create Studio Images
        $this->command->info('📸 Creating studio images...');
        $studioImages = StudioImage::factory(15)->create();
        $this->command->info("✓ Created {$studioImages->count()} studio images");

        // 4. Create Image Cards
        $this->command->info('🎴 Creating image cards...');
        $imageCards = ImageCard::factory(8)->create();
        $this->command->info("✓ Created {$imageCards->count()} image cards");

        // 5. Create Inventories
        $this->command->info('🏢 Creating inventories...');
        $inventories = Inventory::factory(5)->create();
        $this->command->info("✓ Created {$inventories->count()} inventories");

        // 6. Create Products with relationships
        $this->command->info('🛍️  Creating products...');

        foreach ($categories as $category) {
            $productsCount = rand(3, 8);

            for ($i = 0; $i < $productsCount; $i++) {
                $product = Product::factory()->create([
                    'category_id' => $category->id,
                ]);

                // Attach to random inventories with stock quantities
                $randomInventories = $inventories->random(rand(1, 3));
                foreach ($randomInventories as $inventory) {
                    InventoryProduct::create([
                        'product_id' => $product->id,
                        'inventory_id' => $inventory->id,
                        'stock_quantity' => rand(0, 100),
                    ]);
                }

                // Add product images (1-3 per product)
                $imageCount = rand(1, 3);
                for ($j = 0; $j < $imageCount; $j++) {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => 'product-images/placeholder-'.rand(1, 10).'.jpg',
                    ]);
                }
            }
        }

        $totalProducts = Product::count();
        $this->command->info("✓ Created {$totalProducts} products with images and inventory");

        // 7. Create Orders with OrderItems
        $this->command->info('📋 Creating orders...');

        $statuses = ['processing', 'completed'];
        $orderCount = 30;

        for ($i = 0; $i < $orderCount; $i++) {
            $status = fake()->randomElement($statuses);
            $itemsCount = rand(1, 5);
            $subtotal = 0;

            $order = Order::create([
                'name' => 'Order #'.($i + 1).' - '.fake()->name(),
                'status' => $status,
                'subtotal' => 0,
                'discount' => fake()->boolean(30) ? fake()->randomFloat(2, 10, 100) : 0,
                'total_price' => 0,
                'paid_amount' => 0,
                'remaining_amount' => 0,
            ]);

            // Create order items
            for ($j = 0; $j < $itemsCount; $j++) {
                $category = fake()->randomElement(['studio_image', 'image_card', 'product']);

                $orderItemData = [
                    'order_id' => $order->id,
                    'category' => $category,
                    'status' => fake()->randomElement(OrderItem::$workflow),
                ];

                if ($category === 'studio_image') {
                    $studioImage = $studioImages->random();
                    $price = $studioImage->price;

                    $isInstant = fake()->boolean(40) && $studioImage->instant_price > 0;
                    $includeSoftCopy = fake()->boolean(50) && $studioImage->soft_copy_price > 0;
                    $isWithName = fake()->boolean(60) && $studioImage->name_price > 0;

                    if ($isInstant) {
                        $price += $studioImage->instant_price;
                    }
                    if ($includeSoftCopy) {
                        $price += $studioImage->soft_copy_price;
                    }
                    if ($isWithName) {
                        $price += $studioImage->name_price;
                    }

                    $orderItemData = array_merge($orderItemData, [
                        'studio_image_id' => $studioImage->id,
                        'is_instant' => $isInstant,
                        'include_soft_copy' => $includeSoftCopy,
                        'is_with_name' => $isWithName,
                        'price' => $price,
                    ]);
                } elseif ($category === 'image_card') {
                    $imageCard = $imageCards->random();
                    $price = $imageCard->price;

                    $isInstant = fake()->boolean(30) && $imageCard->instant_price > 0;

                    if ($isInstant) {
                        $price += $imageCard->instant_price;
                    }

                    $orderItemData = array_merge($orderItemData, [
                        'image_card_id' => $imageCard->id,
                        'is_instant' => $isInstant,
                        'include_soft_copy' => false,
                        'is_with_name' => false,
                        'price' => $price,
                    ]);
                } else {
                    $product = Product::where('is_active', true)->inRandomOrder()->first();

                    if ($product) {
                        $orderItemData = array_merge($orderItemData, [
                            'product_id' => $product->id,
                            'is_instant' => false,
                            'include_soft_copy' => false,
                            'is_with_name' => false,
                            'price' => $product->price,
                            'status' => 'completed', // Products skip processing
                        ]);
                    } else {
                        continue;
                    }
                }

                OrderItem::create($orderItemData);
                $subtotal += $orderItemData['price'];
            }

            // Update order totals
            $totalPrice = max(0, $subtotal - $order->discount);

            if ($status === 'completed') {
                $paidAmount = $totalPrice;
                $remainingAmount = 0;
            } else {
                $paidAmount = fake()->boolean(70) ? fake()->randomFloat(2, $totalPrice * 0.3, $totalPrice * 0.9) : 0;
                $remainingAmount = max(0, $totalPrice - $paidAmount);
            }

            $order->update([
                'subtotal' => $subtotal,
                'total_price' => $totalPrice,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
            ]);

            // Create income transaction for paid amounts
            if ($paidAmount > 0) {
                Transaction::create([
                    'type' => 'income',
                    'amount' => $paidAmount,
                    'transaction_date' => now()->subDays(rand(0, 30)),
                    'user_id' => $allUsers->random()->id,
                    'order_id' => $order->id,
                    'notes' => $status === 'completed' ? 'Full payment - Order completed' : 'Partial payment',
                ]);
            }
        }

        $this->command->info("✓ Created {$orderCount} orders with items");

        // 8. Create expense transactions
        $this->command->info('💰 Creating expense transactions...');

        $expenseCategories = [
            'Office Supplies',
            'Equipment Purchase',
            'Rent Payment',
            'Utilities',
            'Marketing',
            'Staff Salaries',
            'Maintenance',
            'Software Subscription',
        ];

        for ($i = 0; $i < 25; $i++) {
            Transaction::create([
                'type' => 'expense',
                'amount' => fake()->randomFloat(2, 50, 5000),
                'transaction_date' => now()->subDays(rand(0, 60)),
                'user_id' => $allUsers->random()->id,
                'notes' => fake()->randomElement($expenseCategories),
            ]);
        }

        $totalTransactions = Transaction::count();
        $this->command->info("✓ Created {$totalTransactions} transactions (income + expenses)");

        // Summary
        $this->command->newLine();
        $this->command->info('✅ Database seeding completed successfully!');
        $this->command->newLine();
        $this->command->table(
            ['Model', 'Count'],
            [
                ['Users', User::count()],
                ['Categories', Category::count()],
                ['Studio Images', StudioImage::count()],
                ['Image Cards', ImageCard::count()],
                ['Inventories', Inventory::count()],
                ['Products', Product::count()],
                ['Orders', Order::count()],
                ['Order Items', OrderItem::count()],
                ['Transactions', Transaction::count()],
            ]
        );
        $this->command->newLine();
        $this->command->info('📧 Default Users:');
        $this->command->table(
            ['Email', 'Password', 'Role'],
            [
                ['admin@example.com', 'password', 'Admin'],
                ['photographer@example.com', 'password', 'Photographer'],
                ['editor@example.com', 'password', 'Editor'],
                ['cashier@example.com', 'password', 'Cashier'],
            ]
        );
    }
}
