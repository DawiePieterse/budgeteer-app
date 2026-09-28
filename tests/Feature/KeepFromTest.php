<?php

use App\Models\Account;
use App\Models\Person;
use App\Models\Settlement;
use App\Models\Transaction;

it('deletes what is older than the day and skips it from then on', function () {
    $user = member();
    $household = $user->household;
    $account = Account::factory()->create(['household_id' => $household->id]);
    $old = Transaction::factory()->for($account)->create(['posted_on' => '2026-06-30']);
    $pair = Transaction::factory()->for($account)->create(['posted_on' => '2026-07-01', 'is_transfer' => true, 'transfer_pair_id' => $old->id]);
    $person = Person::create(['household_id' => $household->id, 'name' => 'Sam']);
    Settlement::create(['household_id' => $household->id, 'person_id' => $person->id, 'amount_cents' => 100, 'received_on' => '2026-06-15', 'user_id' => $user->id]);
    Settlement::create(['household_id' => $household->id, 'person_id' => $person->id, 'amount_cents' => 200, 'received_on' => '2026-07-15', 'user_id' => $user->id]);

    $this->artisan('budgeteer:keep-from 2026-07-01')
        ->expectsOutputToContain('This deletes 1 transactions and 1 repayments')
        ->expectsConfirmation('Delete them? This cannot be undone.', 'yes')
        ->assertSuccessful();

    expect(Transaction::withoutGlobalScopes()->pluck('id')->all())->toBe([$pair->id])
        ->and($pair->fresh()->transfer_pair_id)->toBeNull()
        ->and(Settlement::withoutGlobalScopes()->pluck('amount_cents')->all())->toBe([200])
        ->and($household->fresh()->keep_from->toDateString())->toBe('2026-07-01');

    // A statement reaching back before the day only brings in what is after it.
    $this->actingAs($user);
    $this->post('/statements/preview', ['text' => fixtureText('standard-bank')])->assertSee('Import 6 transactions');
    $household->update(['keep_from' => '2026-07-04']);
    $this->post('/statements/preview', ['text' => fixtureText('standard-bank')])
        ->assertSee('2 are dated before 4 July 2026')
        ->assertSee('Import 4 transactions');
    $this->post('/statements');
    expect(Transaction::where('posted_on', '<', '2026-07-04')->count())->toBe(1); // only the kept 1 July line
});

it('deletes nothing unless confirmed', function () {
    $user = member();
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    Transaction::factory()->for($account)->create(['posted_on' => '2026-06-30']);

    $this->artisan('budgeteer:keep-from 2026-07-01')
        ->expectsConfirmation('Delete them? This cannot be undone.', 'no')
        ->assertSuccessful();

    expect(Transaction::withoutGlobalScopes()->count())->toBe(1)
        ->and($user->household->fresh()->keep_from)->toBeNull();
});
