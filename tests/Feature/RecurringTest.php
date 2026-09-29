<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringMark;
use App\Models\RecurringPayment;
use App\Models\Transaction;
use App\Recurring\Occurrence;
use App\Recurring\RecurringMatcher;
use App\Recurring\RecurringSchedule;
use App\Recurring\RecurringSuggestions;
use App\Services\BudgetPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

function payOn(Account $account, string $on, int $cents, string $description, string $key): Transaction
{
    return Transaction::factory()->for($account)->create(['posted_on' => $on, 'amount_cents' => $cents, 'description' => $description, 'merchant_key' => $key, 'kind' => 'debit_order']);
}

function recurring(int $householdId, array $attributes): RecurringPayment
{
    return RecurringPayment::withoutGlobalScopes()->create($attributes + [
        'household_id' => $householdId, 'frequency' => 'monthly', 'day' => 1, 'amount_varies' => false, 'active' => true,
    ]);
}

it('links payments by whole words, so DISC is not DISCINSURE', function () {
    $user = member();
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    $medical = Category::withoutGlobalScopes()->where('household_id', $user->household_id)->where('name', 'Medical')->sole();
    $aid = recurring($user->household_id, ['name' => 'Medical aid', 'match_text' => 'DISC', 'amount_cents' => 938300, 'category_id' => $medical->id]);
    $premium = payOn($account, '2026-07-01', -938300, 'DISC PREM 0001554157-338614243', 'DISC');
    $insure = payOn($account, '2026-07-01', -369292, 'DISCINSURE0000 -1234', 'DISCINSURE');

    expect(app(RecurringMatcher::class)->link($user->household_id))->toBe(1)
        ->and($premium->fresh()->recurring_payment_id)->toBe($aid->id)
        ->and($premium->fresh()->category_id)->toBe($medical->id)   // given the budget line
        ->and($insure->fresh()->recurring_payment_id)->toBeNull();
});

it('gives each due date a status: paid, changed, due, late, skipped or paid by hand', function () {
    $user = member();
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    $pps = recurring($user->household_id, ['name' => 'PPS', 'match_text' => 'PPS', 'amount_cents' => 600000, 'day' => 1]);
    $aid = recurring($user->household_id, ['name' => 'Medical aid', 'match_text' => 'DISC', 'amount_cents' => 938300, 'day' => 1]);
    $levies = recurring($user->household_id, ['name' => 'Levies', 'match_text' => 'LCE OWNERS', 'amount_cents' => 164100, 'day' => 3]);
    $storage = recurring($user->household_id, ['name' => 'Storage', 'match_text' => 'OWEN', 'amount_cents' => 130000, 'day' => 25]);
    $church = recurring($user->household_id, ['name' => 'Church', 'match_text' => 'NG KERK', 'amount_cents' => 100000, 'day' => 5]);
    $gym = recurring($user->household_id, ['name' => 'Gym', 'match_text' => 'GYM', 'amount_cents' => 50000, 'day' => 6]);
    payOn($account, '2026-09-02', -863102, 'PPS 12345 0Y4Q1V', 'PPS');                 // R8,631.02, expected R6,000
    payOn($account, '2026-09-01', -938300, 'DISC PREM 0001554157', 'DISC');            // as expected
    RecurringMark::create(['recurring_payment_id' => $church->id, 'due_on' => '2026-09-05', 'status' => 'skipped', 'user_id' => $user->id]);
    RecurringMark::create(['recurring_payment_id' => $gym->id, 'due_on' => '2026-09-06', 'status' => 'paid', 'user_id' => $user->id]);
    app(RecurringMatcher::class)->link($user->household_id);

    $occurrences = app(RecurringSchedule::class)->occurrences(
        RecurringPayment::withoutGlobalScopes()->get(),
        BudgetPeriod::containing(CarbonImmutable::parse('2026-09-15'), 1),
        CarbonImmutable::parse('2026-09-15'),
    );
    $status = collect($occurrences)->mapWithKeys(fn (Occurrence $o) => [$o->payment->name => $o->status])->all();

    expect($status)->toBe([
        'Medical aid' => 'paid', 'PPS' => 'changed', 'Levies' => 'late', 'Church' => 'skipped', 'Gym' => 'paid_by_hand', 'Storage' => 'due',
    ]);
});

it('expects a weekly payment every week', function () {
    $user = member();
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    $cleaner = recurring($user->household_id, ['name' => 'Cleaner', 'match_text' => 'LIEBENBERG', 'amount_cents' => 40000, 'frequency' => 'weekly', 'day' => 3]);
    foreach (['2026-09-02', '2026-09-09', '2026-09-17'] as $on) {
        payOn($account, $on, -40000, 'M LIEBENBERG MARLIZE', 'LIEBENBERG');
    }
    app(RecurringMatcher::class)->link($user->household_id);

    $occurrences = app(RecurringSchedule::class)->occurrences(collect([$cleaner]), BudgetPeriod::containing(CarbonImmutable::parse('2026-09-15'), 1), CarbonImmutable::parse('2026-09-30'));

    expect(collect($occurrences)->map(fn ($o) => $o->dueOn->format('j').':'.$o->status)->all())
        ->toBe(['2:paid', '9:paid', '16:paid', '23:late', '30:due']);
});

it('suggests payments that come back every month or week', function () {
    $user = member();
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    foreach (['07', '08', '09'] as $m) {
        payOn($account, "2026-{$m}-01", -195105, 'MOMENTUM 12345 FH', 'MOMENTUM');
        payOn($account, "2026-{$m}-10", -((int) "2{$m}00") * 10, 'WOOLWORTHS', 'WOOLWORTHS');   // food, but also:
        payOn($account, "2026-{$m}-20", -99000, 'WOOLWORTHS', 'WOOLWORTHS');                     // amounts too different
        foreach ([2, 9, 16, 23] as $d) {
            payOn($account, sprintf('2026-%s-%02d', $m, $d), -40000, 'M LIEBENBERG MARLIZE', 'LIEBENBERG');
        }
    }

    $suggestions = collect(app(RecurringSuggestions::class)->for($user->household_id, CarbonImmutable::parse('2026-09-28')))->keyBy('match_text');

    expect($suggestions->keys()->sort()->values()->all())->toBe(['LIEBENBERG', 'MOMENTUM'])
        ->and($suggestions['MOMENTUM'])->toMatchArray(['frequency' => 'monthly', 'day' => 1, 'amount_cents' => 195105, 'amount_varies' => false])
        ->and($suggestions['LIEBENBERG'])->toMatchArray(['frequency' => 'weekly', 'amount_cents' => 40000]);
});

it('adds a suggestion, links its history and updates a changed amount', function () {
    Carbon::setTestNow('2026-09-15');
    $user = member();
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    $payment = payOn($account, '2026-09-02', -863102, 'PPS 12345 0Y4Q1V', 'PPS');
    $this->actingAs($user);

    $this->post('/recurring', ['name' => 'PPS', 'match_text' => 'PPS', 'amount' => '6000', 'frequency' => 'monthly', 'day' => 1])
        ->assertSessionHas('status', fn ($s) => str_contains($s, '1 earlier payment linked'));
    $pps = RecurringPayment::sole();
    $this->get('/recurring')->assertSee('⚠ Amount changed')->assertSee('expected R6,000.00');
    $this->get('/')->assertSee('Recurring payments')->assertSee('⚠ Amount changed');

    $this->post("/recurring/{$pps->id}/use-amount/{$payment->id}")->assertRedirect();
    expect($pps->fresh()->amount_cents)->toBe(863102);
    $this->get('/recurring')->assertSee('✓ Paid');
    Carbon::setTestNow();
});

it('marks a late payment as paid elsewhere, and undoes it', function () {
    Carbon::setTestNow('2026-09-20');
    $user = member();
    $this->actingAs($user);
    $this->post('/recurring', ['name' => 'Electricity', 'match_text' => 'PREPAID', 'amount' => '200', 'frequency' => 'monthly', 'day' => 5]);
    $payment = RecurringPayment::sole();
    $this->get('/recurring')->assertSee('⚠ Late');

    $this->post("/recurring/{$payment->id}/mark", ['due_on' => '2026-09-05', 'status' => 'paid']);
    $this->get('/recurring')->assertSee('✓ Paid (by hand)');
    $this->post("/recurring/{$payment->id}/mark", ['due_on' => '2026-09-05', 'status' => 'clear']);
    $this->get('/recurring')->assertSee('⚠ Late');
    Carbon::setTestNow();
});

it('links new statement lines to recurring payments as they are imported', function () {
    $user = member();
    $this->actingAs($user);
    $this->post('/recurring', ['name' => 'Medical aid', 'match_text' => 'HEALTHCO', 'amount' => '3210.50', 'frequency' => 'monthly', 'day' => 5]);

    $this->post('/statements/preview', ['text' => fixtureText('standard-bank')]);
    $this->post('/statements');

    expect(Transaction::where('description', 'like', 'HEALTHCO%')->sole()->recurring_payment_id)->toBe(RecurringPayment::sole()->id);
});

it('edits and stops tracking a recurring payment', function () {
    $user = member();
    $this->actingAs($user);
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    $t = payOn($account, '2026-09-02', -40000, 'M LIEBENBERG MARLIZE', 'LIEBENBERG');
    $this->post('/recurring', ['name' => 'Cleaner', 'match_text' => 'LIEBENBERG', 'amount' => '400', 'frequency' => 'weekly', 'day' => 3]);
    $r = RecurringPayment::sole();

    $this->post("/recurring/{$r->id}", ['name' => 'Cleaning', 'match_text' => 'MARLIZE', 'amount' => '450', 'frequency' => 'weekly', 'day' => 3, 'active' => 1])->assertSessionHasNoErrors();
    expect($r->fresh()->name)->toBe('Cleaning')->and($t->fresh()->recurring_payment_id)->toBe($r->id);

    $this->post("/recurring/{$r->id}/delete");
    expect(RecurringPayment::count())->toBe(0)->and($t->fresh()->recurring_payment_id)->toBeNull();
});

it('keeps recurring payments to their own household', function () {
    $theirs = member();
    $r = recurring($theirs->household_id, ['name' => 'Theirs', 'match_text' => 'X', 'amount_cents' => 100]);

    $this->actingAs(member());
    $this->get('/recurring')->assertDontSee('Theirs');
    $this->post("/recurring/{$r->id}/delete")->assertNotFound();
    $this->post("/recurring/{$r->id}/mark", ['due_on' => '2026-09-01', 'status' => 'skipped'])->assertNotFound();
});
