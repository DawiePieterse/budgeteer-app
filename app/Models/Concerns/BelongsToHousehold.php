<?php

namespace App\Models\Concerns;

use App\Models\Household;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Every query on a household's data is limited to the signed-in user's household,
 * and new rows join that household unless one is given.
 */
trait BelongsToHousehold
{
    public static function bootBelongsToHousehold(): void
    {
        static::addGlobalScope('household', function (Builder $query): void {
            $user = Auth::user();
            if ($user !== null) {
                $query->where($query->qualifyColumn('household_id'), $user->household_id);
            }
        });

        static::creating(function (self $model): void {
            $user = Auth::user();
            if ($model->household_id === null && $user !== null) {
                $model->household_id = $user->household_id;
            }
        });
    }

    /** @return BelongsTo<Household, $this> */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }
}
