<?php

use App\Enums\Bank;
use App\Enums\TransactionKind;
use App\Enums\TransactionSource;
use App\Gmail\Parsers\ParsedEmail;
use App\Models\Account;
use App\Models\Card;
use App\Models\Person;
use App\Models\Settlement;
use App\Models\Transaction;
use App\Services\PersonBalance;
use App\Transactions\EmailTransactions;
use Carbon\CarbonImmutable;

/** A household with a Discovery account, a main card and an extra card. */
function withCards(): array
{
    $user = member();
    $account = Account::factory()->discovery()->create(['household_id' => $user->household_id, 'number_ending' => '1234']);
    $mine = Card::create(['household_id' => $user->household_id, 'account_id' => $account->id, 'number_ending' => '4321']);
    $his = Card::create(['household_id' => $user->household_id, 'account_id' => $account->id, 'number_ending' => '5678', 'holder_name' => 'Sam Smith']);

    return [$user, $account, $mine, $his];
}

it('charges a card to someone new and keeps it out of the budget', function () {
    [$user, $account, $mine, $his] = withCards();
    Transaction::factory()->for($account)->create(['card_id' => $his->id, 'posted_on' => today(), 'amount_cents' => -50000, 'description' => 'GAME STORE', 'merchant_key' => 'GAME']);
    Transaction::factory()->for($account)->create(['card_id' => $mine->id, 'posted_on' => today(), 'amount_cents' => -12300, 'description' => 'WOOLWORTHS']);

    $this->actingAs($user)->post("/cards/{$his->id}", ['owner' => 'new', 'new_person' => 'Sam Smith'])->assertRedirect();

    $sam = Person::sole();
    expect($his->fresh()->charge_to_person_id)->toBe($sam->id)
        ->and(Transaction::where('description', 'GAME STORE')->sole()->person_id)->toBe($sam->id)
        ->and(Transaction::where('description', 'WOOLWORTHS')->sole()->person_id)->toBeNull();

    $this->get('/?in='.today()->toDateString())
        ->assertSee('R123.00')          // our spending
        ->assertDontSee('R500.00')      // his purchase, before his start date, is neither spending nor owed
        ->assertSee('Owed to us');
    $this->get('/categorise')->assertDontSee('GAME');
});

it('counts purchases after the opening balance, less refunds and repayments', function () {
    [$user, $account, , $his] = withCards();
    $sam = Person::create(['household_id' => $user->household_id, 'name' => 'Sam Smith', 'opening_balance_cents' => 100000, 'opening_balance_on' => '2026-09-01']);
    $his->update(['charge_to_person_id' => $sam->id]);
    $t = fn (string $on, int $cents) => Transaction::factory()->for($account)->create(['card_id' => $his->id, 'person_id' => $sam->id, 'posted_on' => $on, 'amount_cents' => $cents]);
    $t('2026-08-31', -99900);   // before: already in the opening balance
    $t('2026-09-02', -30000);
    $t('2026-09-03', 5000);     // refund
    Settlement::create(['household_id' => $user->household_id, 'person_id' => $sam->id, 'amount_cents' => 20000, 'received_on' => '2026-09-05', 'user_id' => $user->id]);

    expect(app(PersonBalance::class)->owed($sam))->toBe(100000 + 30000 - 5000 - 20000);
});

it('charges new card emails straight to the person', function () {
    [$user, $account, , $his] = withCards();
    $sam = Person::create(['household_id' => $user->household_id, 'name' => 'Sam Smith', 'opening_balance_on' => '2026-09-01']);
    $his->update(['charge_to_person_id' => $sam->id]);

    [$transaction] = app(EmailTransactions::class)->record(new ParsedEmail(
        Bank::Discovery, TransactionKind::Purchase, 'TAKEALOT', -129900, '1234', '5678', 'Sam Smith', CarbonImmutable::parse('2026-09-27 16:19'),
    ), $user->household_id);

    expect($transaction->person_id)->toBe($sam->id)
        ->and($transaction->source)->toBe(TransactionSource::Email)
        ->and(app(PersonBalance::class)->owed($sam))->toBe(129900);
});

it('offers his payment into our account as a repayment, and takes it out of income', function () {
    [$user, $account, , $his] = withCards();
    $cheque = Account::factory()->create(['household_id' => $user->household_id]);
    $sam = Person::create(['household_id' => $user->household_id, 'name' => 'Sam Smith', 'opening_balance_cents' => 300000, 'opening_balance_on' => today()->subMonth(), 'payment_reference' => 'sam']);
    $payment = Transaction::factory()->for($cheque)->create(['posted_on' => today(), 'amount_cents' => 100000, 'description' => 'S SMITH SAM REPAY', 'merchant_key' => 'SMITH']);

    $this->actingAs($user)->get("/people/{$sam->id}")->assertSee('Is this Sam Smith paying back?')->assertSee('S SMITH SAM REPAY');
    $this->post("/people/{$sam->id}/settlements/from/{$payment->id}")->assertRedirect();

    expect(app(PersonBalance::class)->owed($sam))->toBe(200000)
        ->and($payment->fresh()->person_id)->toBe($sam->id);
    $this->get("/people/{$sam->id}")->assertDontSee('Is this Sam Smith paying back?')->assertSee('R2,000.00');
    $this->get('/?in='.today()->toDateString())->assertDontSee('R1,000.00');

    // Removing the repayment puts the payment back as ordinary money in.
    $settlement = Settlement::sole();
    $this->post("/people/{$sam->id}/settlements/{$settlement->id}/delete");
    expect($payment->fresh()->person_id)->toBeNull()
        ->and(app(PersonBalance::class)->owed($sam))->toBe(300000);
});

it('records a cash repayment and edits the details', function () {
    [$user] = withCards();
    $sam = Person::create(['household_id' => $user->household_id, 'name' => 'Sam', 'opening_balance_on' => '2026-09-01']);

    $this->actingAs($user)->post("/people/{$sam->id}", [
        'name' => 'Sam Smith', 'phone' => '082 123 4567', 'opening_balance' => '1500.50', 'opening_balance_on' => '2026-09-15', 'payment_reference' => 'SAM',
    ])->assertSessionHasNoErrors();
    $this->post("/people/{$sam->id}/settlements", ['amount' => '500', 'received_on' => '2026-09-20', 'note' => 'cash'])->assertSessionHasNoErrors();

    expect(app(PersonBalance::class)->owed($sam->fresh()))->toBe(100050);
    $this->get("/people/{$sam->id}")
        ->assertSee('R1,000.50')
        ->assertSee('https://wa.me/27821234567', false);
});

it('moves a single transaction to a person from the transaction page', function () {
    [$user, $account] = withCards();
    $sam = Person::create(['household_id' => $user->household_id, 'name' => 'Sam', 'opening_balance_on' => '2026-09-01']);
    $t = Transaction::factory()->for($account)->create(['posted_on' => '2026-09-10', 'amount_cents' => -7000]);

    $this->actingAs($user)->post("/transactions/{$t->id}", ['person_id' => $sam->id, 'is_transfer' => 0])->assertRedirect();

    expect($t->fresh()->person_id)->toBe($sam->id)
        ->and(app(PersonBalance::class)->owed($sam))->toBe(7000);
});

it('puts a card back in the budget', function () {
    [$user, $account, , $his] = withCards();
    $sam = Person::create(['household_id' => $user->household_id, 'name' => 'Sam']);
    $his->update(['charge_to_person_id' => $sam->id]);
    $t = Transaction::factory()->for($account)->create(['card_id' => $his->id, 'person_id' => $sam->id]);

    $this->actingAs($user)->post("/cards/{$his->id}", ['owner' => 'household']);

    expect($his->fresh()->charge_to_person_id)->toBeNull()->and($t->fresh()->person_id)->toBeNull();
});

it('keeps people and repayments to their own household', function () {
    [$owner] = withCards();
    $sam = Person::create(['household_id' => $owner->household_id, 'name' => 'Sam']);
    $settlement = Settlement::create(['household_id' => $owner->household_id, 'person_id' => $sam->id, 'amount_cents' => 100, 'received_on' => '2026-09-01', 'user_id' => $owner->id]);
    [$other, , , $otherCard] = withCards();

    $this->actingAs($other);
    $this->get("/people/{$sam->id}")->assertNotFound();
    $this->post("/people/{$sam->id}/settlements", ['amount' => 1, 'received_on' => '2026-09-01'])->assertNotFound();
    $this->post("/people/{$sam->id}/settlements/{$settlement->id}/delete")->assertNotFound();
    $this->post("/cards/{$otherCard->id}", ['owner' => (string) $sam->id])->assertSessionHasErrors('owner');

    expect(Settlement::withoutGlobalScopes()->count())->toBe(1);
});
