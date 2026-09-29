<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $household_id
 * @property string $name
 * @property string $email
 * @property string|null $google_id
 * @property string|null $avatar_url
 * @property Carbon|null $last_signed_in_at
 * @property bool $notify_recurring
 * @property bool $notify_budget
 * @property bool $notify_gmail
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected $fillable = ['household_id', 'name', 'email', 'google_id', 'avatar_url', 'last_signed_in_at', 'notify_recurring', 'notify_budget', 'notify_gmail'];

    protected $hidden = ['remember_token'];

    protected function casts(): array
    {
        return [
            'last_signed_in_at' => 'datetime',
            'notify_recurring' => 'boolean',
            'notify_budget' => 'boolean',
            'notify_gmail' => 'boolean',
        ];
    }

    /** @return HasMany<PushSubscription, $this> */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /** @return BelongsTo<Household, $this> */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }
}
