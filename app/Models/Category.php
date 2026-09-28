<?php

namespace App\Models;

use App\Enums\CategoryKind;
use App\Models\Concerns\BelongsToHousehold;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $household_id
 * @property string $name
 * @property CategoryKind $kind
 * @property int $sort
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use BelongsToHousehold, HasFactory;

    protected $fillable = ['household_id', 'name', 'kind', 'sort'];

    protected function casts(): array
    {
        return ['kind' => CategoryKind::class];
    }
}
