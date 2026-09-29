<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $recurring_payment_id
 * @property Carbon $due_on
 * @property string $status
 * @property int $user_id
 */
class RecurringMark extends Model
{
    public const SKIPPED = 'skipped';

    public const PAID = 'paid';

    protected $fillable = ['recurring_payment_id', 'due_on', 'status', 'user_id'];

    protected function casts(): array
    {
        return ['due_on' => 'date'];
    }
}
