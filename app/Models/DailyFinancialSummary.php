<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyFinancialSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'summary_date',
        'total_sales',
        'total_income',
        'total_expenses',
        'net_profit',
        'payments_received',
        'payments_made',
        'refunds_issued',
        'outstanding_receivables_eod',
        'outstanding_payables_eod',
        'cash_balance_eod',
    ];

    protected $casts = [
        'summary_date' => 'date',
        'total_sales' => 'decimal:2',
        'total_income' => 'decimal:2',
        'total_expenses' => 'decimal:2',
        'net_profit' => 'decimal:2',
        'payments_received' => 'decimal:2',
        'payments_made' => 'decimal:2',
        'refunds_issued' => 'decimal:2',
        'outstanding_receivables_eod' => 'decimal:2',
        'outstanding_payables_eod' => 'decimal:2',
        'cash_balance_eod' => 'decimal:2',
    ];
}
