<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use App\Models\Concerns\HasOwnerColour;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $household_id
 * @property string $name
 * @property string|null $colour
 * @property int|null $budget_cents
 */
class Project extends Model
{
    use BelongsToHousehold, HasOwnerColour;

    protected $fillable = ['household_id', 'name', 'colour', 'budget_cents'];

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
