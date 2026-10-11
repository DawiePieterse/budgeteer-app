<?php

namespace App\Models;

use App\Enums\CategoryKind;
use App\Models\Concerns\BelongsToHousehold;
use App\Support\CategoryIcons;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $household_id
 * @property string $name
 * @property CategoryKind $kind
 * @property int|null $category_group_id
 * @property int|null $budget_cents
 * @property string|null $icon
 * @property int $sort
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use BelongsToHousehold, HasFactory;

    protected $fillable = ['household_id', 'name', 'kind', 'category_group_id', 'budget_cents', 'icon', 'sort'];

    protected function casts(): array
    {
        return ['kind' => CategoryKind::class];
    }

    /**
     * The household's own group this line is in, if any.
     *
     * @return BelongsTo<CategoryGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(CategoryGroup::class, 'category_group_id');
    }

    /** The icon chosen for this line, or one guessed from its name. */
    public function iconName(): string
    {
        return $this->icon !== null && isset(CategoryIcons::ICONS[$this->icon]) ? $this->icon : CategoryIcons::guess($this->name);
    }
}
