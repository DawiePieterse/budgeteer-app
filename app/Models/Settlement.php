<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Money a person paid back.
 *
 * @property int $id
 * @property int $household_id
 * @property int $person_id
 * @property int $amount_cents
 * @property Carbon $received_on
 * @property int|null $transaction_id
 * @property string|null $note
 * @property int $user_id
 */
class Settlement extends Model
{
    use BelongsToHousehold;

    protected $fillable = ['household_id', 'person_id', 'amount_cents', 'received_on', 'transaction_id', 'note', 'user_id'];

    protected function casts(): array
    {
        return ['received_on' => 'date'];
    }

    /** @return BelongsTo<Transaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
