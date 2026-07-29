<?php

namespace Tests\Feature\Finance;

use App\Enums\AccountSubtype;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FinancePolicyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_permission_cannot_update_an_account(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $account = Account::create(['name' => 'Cash Drawer', 'account_type' => AccountType::Asset]);

        $this->assertFalse($user->can('update', $account));
    }

    public function test_user_with_permission_can_update_an_account(): void
    {
        Permission::findOrCreate('update_account', 'web');

        $user = User::factory()->create(['is_admin' => false]);
        $user->givePermissionTo('update_account');
        $account = Account::create(['name' => 'Cash Drawer', 'account_type' => AccountType::Asset]);

        $this->assertTrue($user->can('update', $account));
    }

    public function test_user_without_permission_cannot_delete_a_customer(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $customer = Customer::create(['name' => 'Jane Doe']);

        $this->assertFalse($user->can('delete', $customer));
    }

    public function test_user_with_permission_can_delete_a_customer(): void
    {
        Permission::findOrCreate('delete_customer', 'web');

        $user = User::factory()->create(['is_admin' => false]);
        $user->givePermissionTo('delete_customer');
        $customer = Customer::create(['name' => 'Jane Doe']);

        $this->assertTrue($user->can('delete', $customer));
    }

    public function test_admin_bypasses_policy_checks_regardless_of_permissions(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $account = Account::create([
            'name' => 'Main Bank',
            'account_type' => AccountType::Asset,
            'account_subtype' => AccountSubtype::Bank,
        ]);

        $this->assertTrue($admin->can('update', $account));
        $this->assertTrue($admin->can('delete', $account));
    }
}
