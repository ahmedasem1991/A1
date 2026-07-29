<?php

namespace Tests\Feature\Finance;

use App\Actions\Finance\RecordExpenseAction;
use App\Actions\Finance\RecordPaymentAction;
use App\Actions\Finance\RecordSaleAction;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\DailyFinancialSummary;
use App\Models\Order;
use App\Services\FinancialReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\Concerns\SeedsChartOfAccounts;
use Tests\TestCase;

class FinancialReportServiceTest extends TestCase
{
    use RefreshDatabase, SeedsChartOfAccounts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedChartOfAccounts();
    }

    private function makeSaleAndPayment(string $customerName, float $amount, Carbon $date): void
    {
        $customer = Customer::create(['name' => $customerName]);

        $order = Order::create([
            'name' => $customer->name,
            'customer_id' => $customer->id,
            'subtotal' => $amount,
            'discount' => 0,
            'total_price' => $amount,
            'paid_amount' => 0,
            'remaining_amount' => $amount,
            'status' => 'processing',
        ]);

        // RecordSaleAction posts the sale's journal entry using the Order's
        // created_at timestamp, so it must be backdated to $date for the
        // sale itself (not just the payment) to land in the intended window.
        $order->created_at = $date;
        $order->save();

        $invoice = app(RecordSaleAction::class)->handle($order);

        app(RecordPaymentAction::class)->handle(
            accountId: $this->cashAccount->id,
            amount: $amount,
            paymentDate: $date,
            paymentMethod: PaymentMethod::Cash,
            customerId: $customer->id,
            allocations: [['invoice_id' => $invoice->id, 'amount' => $amount]],
        );
    }

    public function test_it_reads_a_past_date_from_the_cached_daily_summary(): void
    {
        // Pre-populate the daily summary for a past date directly, simulating what
        // finance:refresh-daily-summary would have written the night before.
        DailyFinancialSummary::create([
            'summary_date' => now()->subDays(2)->toDateString(),
            'total_sales' => 300,
            'total_income' => 300,
            'total_expenses' => 0,
            'net_profit' => 300,
            'payments_received' => 300,
            'payments_made' => 0,
            'refunds_issued' => 0,
        ]);

        $pastDate = now()->subDays(2);
        $summary = app(FinancialReportService::class)->summarizeRange($pastDate, $pastDate);

        $this->assertEquals(300, $summary['total_sales']);
        $this->assertEquals(300, $summary['payments_received']);
        $this->assertEquals(300, $summary['net_profit']);
    }

    public function test_it_summarizes_today_live_without_needing_a_refresh(): void
    {
        $this->makeSaleAndPayment('Today Customer', 150, now());

        app(RecordExpenseAction::class)->handle(
            expenseAccountId: $this->expenseAccount->id,
            paidFromAccountId: $this->cashAccount->id,
            amount: 40,
            expenseDate: now(),
            description: 'Today expense',
        );

        $summary = app(FinancialReportService::class)->summarizeRange(now(), now());

        $this->assertEquals(150, $summary['total_sales']);
        $this->assertEquals(150, $summary['total_income']);
        $this->assertEquals(40, $summary['total_expenses']);
        $this->assertEquals(110, $summary['net_profit']);
        $this->assertEquals(150, $summary['payments_received']);
        $this->assertEquals(40, $summary['payments_made']);
    }

    public function test_it_combines_historical_and_live_today_over_a_range(): void
    {
        $this->makeSaleAndPayment('Range Customer', 200, now());

        // Pre-populate a historical row for a prior date so the range spans both paths.
        DailyFinancialSummary::create([
            'summary_date' => now()->subDays(2)->toDateString(),
            'total_sales' => 500,
            'total_income' => 500,
            'total_expenses' => 100,
            'net_profit' => 400,
            'payments_received' => 500,
            'payments_made' => 100,
            'refunds_issued' => 0,
        ]);

        $summary = app(FinancialReportService::class)->summarizeRange(now()->subDays(3), now());

        $this->assertEquals(700, $summary['total_sales']);
        $this->assertEquals(700, $summary['total_income']);
        $this->assertEquals(100, $summary['total_expenses']);
    }

    public function test_outstanding_balances_reflect_current_state(): void
    {
        $customer = Customer::create(['name' => 'Balance Customer']);
        $order = Order::create([
            'name' => $customer->name,
            'customer_id' => $customer->id,
            'subtotal' => 500,
            'discount' => 0,
            'total_price' => 500,
            'paid_amount' => 0,
            'remaining_amount' => 500,
            'status' => 'processing',
        ]);

        $invoice = app(RecordSaleAction::class)->handle($order);

        app(RecordPaymentAction::class)->handle(
            accountId: $this->cashAccount->id,
            amount: 200,
            paymentDate: now(),
            paymentMethod: PaymentMethod::Cash,
            customerId: $customer->id,
            allocations: [['invoice_id' => $invoice->id, 'amount' => 200]],
        );

        $summary = app(FinancialReportService::class)->summarizeRange(now(), now());

        $this->assertEquals(300, $summary['outstanding_receivables']);
        $this->assertEquals(200, $summary['cash_balance']);
    }

    public function test_a_same_day_time_window_only_counts_transactions_inside_it(): void
    {
        $morning = now()->startOfDay()->addHours(9);
        $evening = now()->startOfDay()->addHours(18);

        $this->makeSaleAndPayment('Morning Customer', 100, $morning);
        $this->makeSaleAndPayment('Evening Customer', 250, $evening);

        $summary = app(FinancialReportService::class)->summarizeRange(
            now()->startOfDay()->addHours(8),
            now()->startOfDay()->addHours(12),
        );

        $this->assertEquals(100, $summary['total_sales']);
        $this->assertEquals(100, $summary['payments_received']);
    }

    public function test_a_full_day_range_still_includes_transactions_at_any_time_of_day(): void
    {
        $morning = now()->startOfDay()->addHours(9);
        $evening = now()->startOfDay()->addHours(18);

        $this->makeSaleAndPayment('Morning Customer', 100, $morning);
        $this->makeSaleAndPayment('Evening Customer', 250, $evening);

        $summary = app(FinancialReportService::class)->summarizeRange(now()->startOfDay(), now()->endOfDay());

        $this->assertEquals(350, $summary['total_sales']);
        $this->assertEquals(350, $summary['payments_received']);
    }
}
