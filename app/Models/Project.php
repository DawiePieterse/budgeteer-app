<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $household_id
 * @property string $name
 * @property int|null $budget_cents
 */
class Project extends Model
{
    use BelongsToHousehold;

    protected $fillable = ['household_id', 'name', 'budget_cents'];

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** Money spent on the project, less anything paid back into it. */
    public function spentCents(): int
    {
        return -(int) $this->transactions()->sum('amount_cents');
    }
}
