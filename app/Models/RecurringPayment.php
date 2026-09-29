<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A payment expected every week, month or year, for example a debit order.
 *
 * @property int $id
 * @property int $household_id
 * @property string $name
 * @property string $match_text
 * @property int|null $category_id
 * @property int $amount_cents
 * @property bool $amount_varies
 * @property string $frequency
 * @property int $day
 * @property int|null $month
 * @property bool $active
 */
class RecurringPayment extends Model
{
    use BelongsToHousehold;

    public const WEEKLY = 'weekly';

    public const MONTHLY = 'monthly';

    public const YEARLY = 'yearly';

    /** Amounts within this share of the expected amount count as the same (rounding, small increases). */
    public const TOLERANCE = 0.02;

    protected $fillable = ['household_id', 'name', 'match_text', 'category_id', 'amount_cents', 'amount_varies', 'frequency', 'day', 'month', 'active'];

    protected function casts(): array
    {
        return ['amount_varies' => 'boolean', 'active' => 'boolean'];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** @return HasMany<RecurringMark, $this> */
    public function marks(): HasMany
    {
        return $this->hasMany(RecurringMark::class);
    }

    /** Whether a transaction's description or merchant key contains the match text as whole words. */
    public function matches(string $description, string $merchantKey): bool
    {
        $needle = mb_strtoupper(trim($this->match_text));
        if ($needle === '') {
            return false;
        }

        return mb_strtoupper($merchantKey) === $needle
            || preg_match('/(?<![\p{L}\p{N}])'.preg_quote($needle, '/').'(?![\p{L}\p{N}])/u', mb_strtoupper($description)) === 1;
    }

    public function amountIsExpected(int $cents): bool
    {
        return $this->amount_varies
            || abs(abs($cents) - $this->amount_cents) <= max(1000, (int) round($this->amount_cents * self::TOLERANCE));
    }

    public function describeSchedule(): string
    {
        $weekdays = [1 => 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        return match ($this->frequency) {
            self::WEEKLY => 'Every '.$weekdays[$this->day],
            self::YEARLY => 'Every year on '.$this->day.' '.date('F', mktime(0, 0, 0, (int) $this->month, 1)),
            default => 'Monthly on the '.$this->day.date('S', mktime(0, 0, 0, 1, $this->day)),
        };
    }
}
