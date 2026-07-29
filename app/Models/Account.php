<?php

namespace App\Models;

use App\Enums\AccountSubtype;
use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'account_type',
        'account_subtype',
        'is_bank_account',
        'is_system',
        'description',
        'opening_balance',
        'current_balance_cache',
        'is_active',
    ];

    protected $casts = [
        'account_type' => AccountType::class,
        'account_subtype' => AccountSubtype::class,
        'is_bank_account' => 'boolean',
        'is_system' => 'boolean',
        'opening_balance' => 'decimal:2',
        'current_balance_cache' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function journalEntryLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }
}
