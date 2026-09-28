<?php

namespace App\Models;

use App\Enums\AccountKind;
use App\Enums\Bank;
use App\Models\Concerns\BelongsToHousehold;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $household_id
 * @property Bank $bank
 * @property AccountKind $kind
 * @property string $name
 * @property string $number_ending
 * @property int|null $statement_balance_cents
 * @property Carbon|null $statement_balance_on
 */
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use BelongsToHousehold, HasFactory;

    protected $fillable = ['household_id', 'bank', 'kind', 'name', 'number_ending', 'statement_balance_cents', 'statement_balance_on'];

    protected function casts(): array
    {
        return [
            'bank' => Bank::class,
            'kind' => AccountKind::class,
            'statement_balance_on' => 'date',
        ];
    }

    /** @return HasMany<Card, $this> */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
