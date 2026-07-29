<?php

namespace App\Models;

use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'payment_number',
        'direction',
        'type',
        'payment_method',
        'amount',
        'payment_date',
        'account_id',
        'customer_id',
        'supplier_id',
        'journal_entry_id',
        'recorded_by_user_id',
        'reference',
        'notes',
    ];

    protected $casts = [
        'direction' => PaymentDirection::class,
        'type' => PaymentType::class,
        'payment_method' => PaymentMethod::class,
        'amount' => 'decimal:2',
        'payment_date' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}
