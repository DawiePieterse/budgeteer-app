<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Someone outside the household who can owe money, for example a son with a card on our account.
 *
 * @property int $id
 * @property int $household_id
 * @property string $name
 * @property string|null $phone
 * @property int $opening_balance_cents
 * @property Carbon|null $opening_balance_on
 * @property string|null $payment_reference
 */
class Person extends Model
{
    use BelongsToHousehold;

    protected $table = 'people';

    protected $fillable = ['household_id', 'name', 'phone', 'opening_balance_cents', 'opening_balance_on', 'payment_reference'];

    protected function casts(): array
    {
        return ['opening_balance_on' => 'date'];
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** @return HasMany<Settlement, $this> */
    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class);
    }

    /** @return HasMany<Card, $this> */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class, 'charge_to_person_id');
    }
}
