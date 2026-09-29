<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An online order from a shop's confirmation email, with its items.
 *
 * @property int $id
 * @property int $household_id
 * @property string $shop
 * @property string $order_number
 * @property Carbon $ordered_at
 * @property int $total_cents
 * @property string|null $deliver_to
 * @property int|null $transaction_id
 * @property int|null $ingested_email_id
 */
class Order extends Model
{
    use BelongsToHousehold;

    public const TAKEALOT = 'takealot';

    public const AMAZON = 'amazon';

    protected $fillable = ['household_id', 'shop', 'order_number', 'ordered_at', 'total_cents', 'deliver_to', 'transaction_id', 'ingested_email_id'];

    protected function casts(): array
    {
        return ['ordered_at' => 'datetime'];
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return BelongsTo<Transaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function shopName(): string
    {
        return $this->shop === self::AMAZON ? 'Amazon' : 'Takealot';
    }

    /** The order on the shop's website (the person must be signed in there). */
    public function url(): string
    {
        return $this->shop === self::AMAZON
            ? 'https://www.amazon.co.za/your-orders/order-details?orderID='.rawurlencode($this->order_number)
            : 'https://www.takealot.com/account/orders/'.rawurlencode($this->order_number);
    }

    /** "Kettle, USB-C cable ×2 and 3 more", for a line in a list. */
    public function summary(int $names = 2): string
    {
        $items = $this->items;
        $shown = $items->take($names)->map(fn (OrderItem $i) => $i->name.($i->quantity > 1 ? ' ×'.$i->quantity : ''))->implode(', ');

        return $shown.($items->count() > $names ? ' and '.($items->count() - $names).' more' : '');
    }
}
