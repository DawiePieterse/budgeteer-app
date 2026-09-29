<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $household_id
 * @property string $key
 * @property string $title
 * @property string $body
 * @property Carbon|null $created_at
 */
class SentNotification extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['household_id', 'key', 'title', 'body'];
}
