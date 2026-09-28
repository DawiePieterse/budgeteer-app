<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $household_id
 * @property int $user_id
 * @property string $email
 * @property string $refresh_token
 * @property string|null $access_token
 * @property Carbon|null $access_token_expires_at
 * @property string|null $label_id
 * @property string|null $history_id
 * @property string $status
 * @property string|null $last_error
 * @property Carbon|null $last_synced_at
 */
class GmailConnection extends Model
{
    use BelongsToHousehold;

    public const ACTIVE = 'active';

    public const LABEL_MISSING = 'label_missing';

    public const NEEDS_RELINK = 'needs_relink';

    public const ERROR = 'error';

    /** The Gmail label a filter puts on bank notifications; only these are read. */
    public const LABEL = 'Budgeteer';

    protected $fillable = [
        'household_id', 'user_id', 'email', 'refresh_token', 'access_token', 'access_token_expires_at',
        'label_id', 'history_id', 'status', 'last_error', 'last_synced_at',
    ];

    protected $hidden = ['refresh_token', 'access_token'];

    protected function casts(): array
    {
        return [
            'refresh_token' => 'encrypted',
            'access_token' => 'encrypted',
            'access_token_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
