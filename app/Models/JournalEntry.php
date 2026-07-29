<?php

namespace App\Models;

use App\Enums\JournalEntryType;
use App\Exceptions\ImmutableJournalEntryException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JournalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'entry_date',
        'entry_type',
        'description',
        'sourceable_type',
        'sourceable_id',
        'reverses_journal_entry_id',
        'posted_by_user_id',
        'is_reversed',
        'total_amount',
    ];

    protected $casts = [
        'entry_date' => 'datetime',
        'entry_type' => JournalEntryType::class,
        'is_reversed' => 'boolean',
        'total_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $entry) {
            throw new ImmutableJournalEntryException('Journal entries cannot be updated. Post a reversal entry instead.');
        });

        static::deleting(function (self $entry) {
            throw new ImmutableJournalEntryException('Journal entries cannot be deleted. Post a reversal entry instead.');
        });
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function sourceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function reversesJournalEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_journal_entry_id');
    }

    public function postedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }
}
