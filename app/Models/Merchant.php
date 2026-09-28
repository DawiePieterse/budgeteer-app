<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $household_id
 * @property string $key
 * @property int|null $category_id
 * @property int|null $project_id
 * @property int $times_confirmed
 */
class Merchant extends Model
{
    use BelongsToHousehold;

    protected $fillable = ['household_id', 'key', 'category_id', 'project_id', 'times_confirmed'];

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
