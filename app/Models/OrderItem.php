<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $order_id
 * @property string $name
 * @property int $quantity
 * @property int|null $price_cents
 */
class OrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['order_id', 'name', 'quantity', 'price_cents'];
}
