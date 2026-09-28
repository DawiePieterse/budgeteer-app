<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $household_id
 * @property int $account_id
 * @property int $user_id
 * @property Carbon $period_from
 * @property Carbon $period_to
 * @property int $opening_cents
 * @property int $closing_cents
 * @property int $lines
 * @property int $added
 * @property int $already_there
 * @property int $matched_emails
 * @property string $fingerprint
 * @property Carbon $created_at
 */
class StatementImport extends Model
{
    use BelongsToHousehold;

    protected $fillable = ['household_id', 'account_id', 'user_id', 'period_from', 'period_to', 'opening_cents', 'closing_cents', 'lines', 'added', 'already_there', 'matched_emails', 'fingerprint'];

    protected function casts(): array
    {
        return ['period_from' => 'date', 'period_to' => 'date'];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
