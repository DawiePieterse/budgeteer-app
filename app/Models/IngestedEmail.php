<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $household_id
 * @property int $gmail_connection_id
 * @property string $gmail_message_id
 * @property string|null $sender
 * @property string|null $subject
 * @property Carbon|null $received_at
 * @property string $status
 * @property string|null $note
 * @property int|null $transaction_id
 */
class IngestedEmail extends Model
{
    use BelongsToHousehold;

    public const ADDED = 'added';

    public const MATCHED = 'matched';

    public const IGNORED = 'ignored';

    public const UNRECOGNISED = 'unrecognised';

    public const FAILED = 'failed';

    /** A shop's order confirmation, kept as an order with its items. */
    public const ORDER = 'order';

    protected $fillable = ['household_id', 'gmail_connection_id', 'gmail_message_id', 'sender', 'subject', 'received_at', 'status', 'note', 'transaction_id'];

    protected function casts(): array
    {
        return ['received_at' => 'datetime'];
    }

    /** @return BelongsTo<Transaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
