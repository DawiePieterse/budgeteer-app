<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use App\Support\GroupColours;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A group of budget lines the household makes itself, like a section of its budget spreadsheet
 * ("Home and bills", "Car"). The desktop Overview shows spending per group.
 *
 * @property int $id
 * @property int $household_id
 * @property string $name
 * @property string $colour
 * @property int $sort
 */
class CategoryGroup extends Model
{
    use BelongsToHousehold;

    protected $fillable = ['household_id', 'name', 'colour', 'sort'];

    public static function booted(): void
    {
        // A new group gets the next free colour and goes last.
        static::creating(function (self $group): void {
            if ($group->household_id !== null) {
                $group->colour ??= GroupColours::next($group->household_id);
                $group->sort ??= (int) self::withoutGlobalScopes()->where('household_id', $group->household_id)->max('sort') + 1;
            }
        });
    }

    /** The household's group with this name, whatever its capitals, made if there is none yet. */
    public static function named(string $name): self
    {
        $name = mb_substr(trim($name), 0, 60);

        return self::query()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->first() ?? self::create(['name' => $name]);
    }

    /** @return HasMany<Category, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }
}
