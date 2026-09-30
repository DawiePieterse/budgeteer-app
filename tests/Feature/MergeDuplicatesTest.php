<?php

use App\Enums\TransactionSource;
use App\Models\Account;
use App\Models\IngestedEmail;
use App\Models\Transaction;

it('merges a purchase saved from an email and again from a statement', function () {
    $user = member();
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    $email = Transaction::factory()->for($account)->create([
        'source' => TransactionSource::Email, 'posted_on' => '2026-09-19', 'description' => 'Leroy Merlin Cape Town ZA',
        'merchant_key' => 'LEROY', 'amount_cents' => -39300, 'statement_import_id' => null,
    ]);
    $line = Transaction::factory()->for($account)->create([
        'source' => TransactionSource::Statement, 'posted_on' => '2026-09-26', 'description' => 'LEROY MERLIN',
        'merchant_key' => 'LEROY', 'amount_cents' => -39300,
    ]);
    $other = Transaction::factory()->for($account)->create([
        'source' => TransactionSource::Statement, 'posted_on' => '2026-09-26', 'description' => 'BUILDERS',
        'merchant_key' => 'BUILDERS', 'amount_cents' => -39300,
    ]);
    IngestedEmail::withoutGlobalScopes()->create([
        'household_id' => $user->household_id, 'gmail_connection_id' => linkedGmail($user)->id, 'gmail_message_id' => 'm1',
        'status' => 'added', 'transaction_id' => $email->id,
    ]);

    $this->artisan('budgeteer:merge-duplicates')
        ->expectsConfirmation('Merge these 1 into one transaction each? This cannot be undone.', 'yes')
        ->assertSuccessful();

    expect(Transaction::withoutGlobalScopes()->pluck('id')->sort()->values()->all())->toBe([$line->id, $other->id])
        ->and(IngestedEmail::withoutGlobalScopes()->sole()->transaction_id)->toBe($line->id);
});

it('says so when there is nothing to merge', function () {
    $this->artisan('budgeteer:merge-duplicates')->expectsOutput('No duplicates found.')->assertSuccessful();
});
