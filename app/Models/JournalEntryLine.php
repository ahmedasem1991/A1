<?php

namespace App\Models;

use App\Enums\DebitCredit;
use App\Exceptions\ImmutableJournalEntryException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntryLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'type',
        'amount',
        'entry_date',
        'customer_id',
        'supplier_id',
        'memo',
    ];

    protected $casts = [
        'type' => DebitCredit::class,
        'amount' => 'decimal:2',
        'entry_date' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $line) {
            throw new ImmutableJournalEntryException('Journal entry lines cannot be updated. Post a reversal entry instead.');
        });

        static::deleting(function (self $line) {
            throw new ImmutableJournalEntryException('Journal entry lines cannot be deleted. Post a reversal entry instead.');
        });
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

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
}
