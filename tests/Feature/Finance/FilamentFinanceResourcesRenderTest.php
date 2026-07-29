<?php

namespace Tests\Feature\Finance;

use App\Filament\Pages\FinancialReports;
use App\Filament\Resources\AccountResource\Pages\ListAccounts;
use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Filament\Resources\InvoiceResource\Pages\ListInvoices;
use App\Filament\Resources\JournalEntryResource\Pages\ListJournalEntries;
use App\Filament\Resources\PaymentResource\Pages\ListPayments;
use App\Filament\Resources\SupplierBillResource\Pages\ListSupplierBills;
use App\Filament\Resources\SupplierResource\Pages\ListSuppliers;
use App\Filament\Widgets\CashPositionOverview;
use App\Filament\Widgets\RecentPaymentsWidget;
use App\Filament\Widgets\RevenueExpenseChart;
use App\Filament\Widgets\TopCustomersWidget;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentFinanceResourcesRenderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($this->admin);
    }

    public function test_account_resource_list_renders(): void
    {
        $account = Account::create(['name' => 'Test Account', 'account_type' => 'asset']);

        Livewire::test(ListAccounts::class)
            ->assertCanSeeTableRecords([$account])
            ->assertSuccessful();
    }

    public function test_customer_resource_list_renders(): void
    {
        $customer = Customer::create(['name' => 'Test Customer']);

        Livewire::test(ListCustomers::class)
            ->assertCanSeeTableRecords([$customer])
            ->assertSuccessful();
    }

    public function test_supplier_resource_list_renders(): void
    {
        $supplier = Supplier::create(['name' => 'Test Supplier']);

        Livewire::test(ListSuppliers::class)
            ->assertCanSeeTableRecords([$supplier])
            ->assertSuccessful();
    }

    public function test_invoice_resource_list_renders(): void
    {
        $customer = Customer::create(['name' => 'Invoice Customer']);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-000001',
            'customer_id' => $customer->id,
            'invoice_date' => now(),
            'subtotal' => 100,
            'total_amount' => 100,
            'balance_cache' => 100,
            'status' => 'unpaid',
        ]);

        Livewire::test(ListInvoices::class)
            ->assertCanSeeTableRecords([$invoice])
            ->assertSuccessful();
    }

    public function test_supplier_bill_resource_list_renders(): void
    {
        $supplier = Supplier::create(['name' => 'Bill Supplier']);
        $bill = SupplierBill::create([
            'bill_number' => 'BILL-000001',
            'supplier_id' => $supplier->id,
            'bill_date' => now(),
            'total_amount' => 100,
            'balance_cache' => 100,
            'status' => 'unpaid',
        ]);

        Livewire::test(ListSupplierBills::class)
            ->assertCanSeeTableRecords([$bill])
            ->assertSuccessful();
    }

    public function test_payment_resource_list_renders(): void
    {
        $account = Account::create(['name' => 'Cash', 'account_type' => 'asset']);
        $payment = Payment::create([
            'payment_number' => 'PAY-000001',
            'direction' => 'inbound',
            'payment_method' => 'cash',
            'amount' => 100,
            'payment_date' => now(),
            'account_id' => $account->id,
        ]);

        Livewire::test(ListPayments::class)
            ->assertCanSeeTableRecords([$payment])
            ->assertSuccessful();
    }

    public function test_journal_entry_resource_list_renders(): void
    {
        $account = Account::create(['name' => 'Cash', 'account_type' => 'asset']);
        $entry = JournalEntry::create([
            'entry_date' => now(),
            'entry_type' => 'adjustment',
            'description' => 'Test entry',
            'total_amount' => 100,
        ]);
        $entry->lines()->create(['account_id' => $account->id, 'type' => 'debit', 'amount' => 100, 'entry_date' => now()]);

        Livewire::test(ListJournalEntries::class)
            ->assertCanSeeTableRecords([$entry])
            ->assertSuccessful();
    }

    public function test_cash_position_overview_widget_renders(): void
    {
        Account::create(['name' => 'Cash', 'account_type' => 'asset', 'is_bank_account' => true]);

        Livewire::test(CashPositionOverview::class)->assertSuccessful();
    }

    public function test_revenue_expense_chart_widget_renders(): void
    {
        Livewire::test(RevenueExpenseChart::class)->assertSuccessful();
    }

    public function test_top_customers_widget_renders(): void
    {
        Customer::create(['name' => 'Widget Customer']);

        Livewire::test(TopCustomersWidget::class)->assertSuccessful();
    }

    public function test_recent_payments_widget_renders(): void
    {
        $account = Account::create(['name' => 'Cash', 'account_type' => 'asset']);
        Payment::create([
            'payment_number' => 'PAY-000002',
            'direction' => 'inbound',
            'payment_method' => 'cash',
            'amount' => 50,
            'payment_date' => now(),
            'account_id' => $account->id,
        ]);

        Livewire::test(RecentPaymentsWidget::class)->assertSuccessful();
    }

    public function test_financial_reports_page_renders(): void
    {
        Account::create(['name' => 'Cash', 'account_type' => 'asset', 'is_bank_account' => true]);

        Livewire::test(FinancialReports::class)->assertSuccessful();
    }

    public function test_financial_reports_page_preset_switches_range(): void
    {
        Livewire::test(FinancialReports::class)
            ->call('setPreset', 'yesterday')
            ->assertSet('from', now()->subDay()->startOfDay()->toDateTimeString())
            ->assertSet('to', now()->subDay()->endOfDay()->toDateTimeString());
    }

    public function test_financial_reports_visible_form_fields_reflect_the_mounted_default_range(): void
    {
        Livewire::test(FinancialReports::class)
            ->assertFormSet([
                'from' => now()->startOfDay()->toDateTimeString(),
                'to' => now()->endOfDay()->toDateTimeString(),
            ]);
    }

    public function test_financial_reports_visible_form_fields_reflect_a_preset_after_its_clicked(): void
    {
        Livewire::test(FinancialReports::class)
            ->call('setPreset', 'yesterday')
            ->assertFormSet([
                'from' => now()->subDay()->startOfDay()->toDateTimeString(),
                'to' => now()->subDay()->endOfDay()->toDateTimeString(),
            ]);
    }

    public function test_journal_entry_resource_date_filter_scopes_results(): void
    {
        $account = Account::create(['name' => 'Cash', 'account_type' => 'asset']);

        $inRange = JournalEntry::create([
            'entry_date' => now(),
            'entry_type' => 'adjustment',
            'description' => 'In range',
            'total_amount' => 100,
        ]);
        $inRange->lines()->create(['account_id' => $account->id, 'type' => 'debit', 'amount' => 100, 'entry_date' => now()]);

        $outOfRange = JournalEntry::create([
            'entry_date' => now()->subDays(10),
            'entry_type' => 'adjustment',
            'description' => 'Out of range',
            'total_amount' => 50,
        ]);
        $outOfRange->lines()->create(['account_id' => $account->id, 'type' => 'debit', 'amount' => 50, 'entry_date' => now()->subDays(10)]);

        Livewire::test(ListJournalEntries::class)
            ->filterTable('entry_date', ['from' => now()->subDay()->toDateString(), 'until' => now()->toDateString()])
            ->assertCanSeeTableRecords([$inRange])
            ->assertCanNotSeeTableRecords([$outOfRange]);
    }
}
