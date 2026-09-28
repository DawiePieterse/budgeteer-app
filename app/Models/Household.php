<?php

namespace App\Models;

use Database\Factories\HouseholdFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int $period_start_day
 * @property string|null $own_account_names
 */
class Household extends Model
{
    /** @use HasFactory<HouseholdFactory> */
    use HasFactory;

    protected $fillable = ['name', 'period_start_day', 'own_account_names'];

    /** @return list<string> */
    public function ownAccountNames(): array
    {
        $names = preg_split('/[\r\n,]+/', strtoupper((string) $this->own_account_names)) ?: [];

        return array_values(array_filter(array_map('trim', $names)));
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
