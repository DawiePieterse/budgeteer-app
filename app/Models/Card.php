<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $household_id
 * @property int $account_id
 * @property string $number_ending
 * @property string|null $holder_name
 * @property int|null $user_id
 * @property int|null $charge_to_person_id
 */
class Card extends Model
{
    use BelongsToHousehold;

    protected $fillable = ['household_id', 'account_id', 'number_ending', 'holder_name', 'user_id', 'charge_to_person_id'];

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<Person, $this> */
    public function chargeToPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'charge_to_person_id');
    }
}
