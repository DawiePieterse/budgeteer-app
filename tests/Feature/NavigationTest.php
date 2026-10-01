<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Person;
use App\Models\Project;
use App\Models\StatementImport;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

it('shows how many transactions are still to review on the Review tab', function () {
    $user = member();
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    Transaction::factory()->for($account)->count(3)->create(['category_id' => null]);
    Transaction::factory()->for($account)->create(['category_id' => null, 'is_transfer' => true]);   // not to review

    $this->actingAs($user)->get('/transactions')
        ->assertSee('<span class="badge" aria-hidden="true">3</span>', false)
        ->assertSee('3 to review');
});

it('opens the More tab and each settings page', function () {
    $user = member();
    $this->actingAs($user);

    $this->get('/settings')->assertOk()
        ->assertSee('Statements')->assertSee('Recurring payments')->assertSee('People who pay you back')->assertSee('Special projects')
        ->assertSee('Household')->assertSee('Accounts and cards')->assertSee('Bank emails')->assertSee('Phone notifications')
        ->assertSee('Sign out');
    foreach (['/settings/household', '/settings/accounts', '/settings/gmail', '/settings/notifications', '/people', '/projects'] as $page) {
        $this->get($page)->assertOk();
    }
    $this->get('/settings/household')->assertSee($user->email);   // who can sign in
});

it('lists people who pay back, also when they are all square', function () {
    $user = member();
    $mary = Person::create(['household_id' => $user->household_id, 'name' => 'Aunt Mary']);

    $this->actingAs($user)->get('/people')->assertSee('Aunt Mary')->assertSee('All square');
    $this->get('/settings')->assertSee('All square');
    expect($mary->exists)->toBeTrue();
});

it('lists special projects with what is spent of their budget', function () {
    $user = member();
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    $car = Project::create(['household_id' => $user->household_id, 'name' => 'Car rebuild', 'budget_cents' => 8000000]);
    Transaction::factory()->for($account)->create(['project_id' => $car->id, 'amount_cents' => -4830000]);

    $this->actingAs($user)->get('/projects')->assertOk()
        ->assertSee('Car rebuild')->assertSee('R48,300.00')->assertSee('60% of R80,000.00');
    $this->get('/budget')->assertDontSee('Car rebuild');   // projects have their own part of Budget now
});

it('totals each day of transactions, leaving out money moved between own accounts', function () {
    Carbon::setTestNow('2026-07-20');
    $user = member();
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    Transaction::factory()->for($account)->create(['posted_on' => '2026-07-03', 'amount_cents' => -12345]);
    Transaction::factory()->for($account)->create(['posted_on' => '2026-07-03', 'amount_cents' => -10000]);
    Transaction::factory()->for($account)->create(['posted_on' => '2026-07-03', 'amount_cents' => -900000, 'is_transfer' => true]);

    $this->actingAs($user)->get('/transactions')
        ->assertSee('Fri 3 Jul')                                   // the year only when it is not this year
        ->assertSee('<span class="muted">-R223.45</span>', false);
    Carbon::setTestNow();
});

it('offers the suggested category as one tap on Review', function () {
    $user = member();
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    $groceries = Category::withoutGlobalScopes()->where('household_id', $user->household_id)->where('name', 'Groceries')->sole();
    Transaction::factory()->for($account)->create(['description' => 'GROCERIES R US', 'merchant_key' => 'GROCERIES R US', 'category_id' => null, 'amount_cents' => -5000]);

    $this->actingAs($user)->get('/categorise')
        ->assertSee('Groceries R Us')
        ->assertSee('<input type="hidden" name="category" value="'.$groceries->id.'">', false)
        ->assertSee('Something else…');
});

it('reminds on Home and Statements of a statement that should be out', function () {
    Carbon::setTestNow('2026-09-25');
    $user = member();
    $account = Account::factory()->create(['household_id' => $user->household_id, 'name' => 'Cheque account', 'number_ending' => '3445']);
    StatementImport::withoutGlobalScopes()->create(['household_id' => $user->household_id, 'account_id' => $account->id, 'user_id' => $user->id,
        'period_from' => '2026-07-21', 'period_to' => '2026-08-20', 'opening_cents' => 0, 'closing_cents' => 0, 'lines' => 0, 'added' => 0,
        'already_there' => 0, 'matched_emails' => 0, 'fingerprint' => hash('sha256', 'x')]);

    $this->actingAs($user)->get('/')->assertSee('Cheque account ••3445: the one to 20 Sep should be out');
    $this->get('/statements')->assertSee('The statement to 20 Sep should be out');
    $this->get('/settings')->assertSee('statement due');
    Carbon::setTestNow();
});

it('offers an order delivered to someone who pays back as bought for them, in one tap', function () {
    $user = member();
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    $sam = Person::create(['household_id' => $user->household_id, 'name' => 'Sam']);
    $payment = Transaction::factory()->for($account)->create(['amount_cents' => -124900]);
    $payment->order()->create(['household_id' => $user->household_id, 'shop' => 'takealot', 'order_number' => '1', 'total_cents' => 124900, 'ordered_at' => now(), 'deliver_to' => 'Sam Smith']);

    $this->actingAs($user)->get("/transactions/{$payment->id}")
        ->assertSee('This went to Sam.')
        ->assertSee('<input type="hidden" name="person_id" value="'.$sam->id.'">', false);
    $this->post("/transactions/{$payment->id}", ['person_id' => $sam->id, 'category_id' => '', 'is_transfer' => 0])->assertRedirect();
    expect($payment->fresh()->person_id)->toBe($sam->id);
});
