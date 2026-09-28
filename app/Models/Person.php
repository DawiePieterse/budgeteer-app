<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $household_id
 * @property string $name
 * @property string|null $phone
 */
class Person extends Model
{
    use BelongsToHousehold;

    protected $table = 'people';

    protected $fillable = ['household_id', 'name', 'phone'];
}
