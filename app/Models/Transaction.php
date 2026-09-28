<?php

namespace App\Models;

use App\Enums\TransactionKind;
use App\Enums\TransactionSource;
use App\Models\Concerns\BelongsToHousehold;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $household_id
 * @property int $account_id
 * @property int|null $card_id
 * @property TransactionSource $source
 * @property int|null $statement_import_id
 * @property Carbon $posted_on
 * @property string $description
 * @property string|null $bank_type
 * @property string $merchant_key
 * @property int $amount_cents
 * @property string $currency
 * @property TransactionKind $kind
 * @property int|null $category_id
 * @property int|null $person_id
 * @property int|null $project_id
 * @property bool $is_transfer
 * @property int|null $transfer_pair_id
 * @property int|null $balance_after_cents
 * @property int|null $line_on_statement
 * @property int|null $updated_by
 */
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use BelongsToHousehold, HasFactory;

    protected $fillable = [
        'household_id', 'account_id', 'card_id', 'source', 'statement_import_id', 'posted_on', 'occurred_at', 'description',
        'bank_type', 'merchant_key', 'amount_cents', 'currency', 'kind', 'category_id', 'person_id', 'project_id', 'is_transfer',
        'transfer_pair_id', 'balance_after_cents', 'line_on_statement', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'source' => TransactionSource::class,
            'kind' => TransactionKind::class,
            'posted_on' => 'date',
            'occurred_at' => 'datetime',
            'is_transfer' => 'boolean',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** @return HasOne<Settlement, $this> */
    public function settlement(): HasOne
    {
        return $this->hasOne(Settlement::class);
    }

    /** @return BelongsTo<Card, $this> */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
