<?php

namespace Database\Seeders;

use App\Actions\Finance\RecordExpenseAction;
use App\Actions\Finance\RecordPaymentAction;
use App\Actions\Finance\RecordSaleAction;
use App\Enums\PaymentMethod;
use App\Models\Account;
use App\Models\Category;
use App\Models\Customer;
use App\Models\ImageCard;
use App\Models\Inventory;
use App\Models\InventoryProduct;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\StudioImage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'is_admin' => true,
            'password' => Hash::make('password'),
        ]);
        $this->call(ChartOfAccountsSeeder::class);

        /**
        $this->command->info('🌱 Starting comprehensive database seeding...');

        // 1. Create Users with different roles
        $this->command->info('👥 Creating users...');

        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'is_admin' => true,
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

        // 1b. Seed the Chart of Accounts
        $this->command->info('📒 Seeding chart of accounts...');
        $this->call(ChartOfAccountsSeeder::class);
        $cashDrawer = Account::query()->where('is_bank_account', true)->firstOrFail();
        $this->command->info('✓ Chart of accounts seeded');

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

            $customerName = fake()->name();
            $customer = Customer::create([
                'name' => $customerName,
                'phone' => fake()->phoneNumber(),
                'email' => fake()->safeEmail(),
            ]);

            $order = Order::create([
                'name' => 'Order #'.($i + 1).' - '.$customerName,
                'customer_id' => $customer->id,
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
            ]);

            // Post the sale to the ledger, then record the payment(s) received against it
            if ($totalPrice > 0) {
                $invoice = app(RecordSaleAction::class)->handle($order->fresh());

                if ($paidAmount > 0) {
                    app(RecordPaymentAction::class)->handle(
                        accountId: $cashDrawer->id,
                        amount: round($paidAmount, 2),
                        paymentDate: now()->subDays(rand(0, 30)),
                        paymentMethod: PaymentMethod::Cash,
                        customerId: $customer->id,
                        allocations: [['invoice_id' => $invoice->id, 'amount' => round($paidAmount, 2)]],
                        notes: $status === 'completed' ? 'Full payment - Order completed' : 'Partial payment',
                        recordedByUserId: $allUsers->random()->id,
                    );
                }
            }
        }

        $this->command->info("✓ Created {$orderCount} orders with items");

        // 8. Create expenses
        $this->command->info('💰 Creating expenses...');

        $expenseCategories = [
            'Office Supplies',
            'Equipment Purchase',
            'Rent',
            'Utilities',
            'Marketing',
            'Salaries',
            'Maintenance',
            'Software Subscription',
        ];

        for ($i = 0; $i < 25; $i++) {
            $expenseAccount = Account::query()->where('name', fake()->randomElement($expenseCategories))->firstOrFail();

            app(RecordExpenseAction::class)->handle(
                expenseAccountId: $expenseAccount->id,
                paidFromAccountId: $cashDrawer->id,
                amount: fake()->randomFloat(2, 50, 5000),
                expenseDate: now()->subDays(rand(0, 60)),
                description: $expenseAccount->name,
                recordedByUserId: $allUsers->random()->id,
            );
        }

        $totalPayments = Payment::count();
        $this->command->info("✓ Created {$totalPayments} payments (income + expenses)");

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
                ['Customers', Customer::count()],
                ['Accounts', Account::count()],
                ['Payments', Payment::count()],
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
        **/
    }
}
