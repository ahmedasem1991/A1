<?php

namespace App\Models;

use App\Enums\SupplierBillStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierBill extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'bill_number',
        'supplier_id',
        'bill_date',
        'due_date',
        'total_amount',
        'paid_amount_cache',
        'balance_cache',
        'status',
        'expense_account_id',
        'notes',
    ];

    protected $casts = [
        'bill_date' => 'date',
        'due_date' => 'date',
        'total_amount' => 'decimal:2',
        'paid_amount_cache' => 'decimal:2',
        'balance_cache' => 'decimal:2',
        'status' => SupplierBillStatus::class,
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    public function paymentAllocations(): MorphMany
    {
        return $this->morphMany(PaymentAllocation::class, 'allocatable');
    }
}
