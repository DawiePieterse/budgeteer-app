<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;

it('shows the budget period with money in and spending', function () {
    $user = member();
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    $groceries = Category::withoutGlobalScopes()->where('household_id', $user->household_id)->where('name', 'Groceries')->sole();
    Transaction::factory()->for($account)->create(['posted_on' => '2026-07-03', 'amount_cents' => -12345, 'category_id' => $groceries->id]);
    Transaction::factory()->for($account)->create(['posted_on' => '2026-07-04', 'amount_cents' => 500000, 'description' => 'SALARY', 'merchant_key' => 'SALARY']);
    Transaction::factory()->for($account)->create(['posted_on' => '2026-07-05', 'amount_cents' => -900000, 'is_transfer' => true]);

    $this->actingAs($user)->get('/?in=2026-07-15')
        ->assertOk()
        ->assertSee('July 2026')
        ->assertSee('R123.45')
        ->assertSee('R5,000.00')
        ->assertDontSee('R9,000.00');
});

it('lists, searches and edits transactions', function () {
    $user = member();
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    $t = Transaction::factory()->for($account)->create(['description' => 'CHECKERS SIXTY60']);
    $category = Category::withoutGlobalScopes()->where('household_id', $user->household_id)->where('name', 'Groceries')->sole();

    $this->actingAs($user)->get('/transactions?q=sixty')->assertOk()->assertSee('Checkers Sixty60');
    $this->get('/transactions?q=nothing')->assertDontSee('Checkers Sixty60');
    $this->get("/transactions/{$t->id}")->assertOk();
    $this->post("/transactions/{$t->id}", ['category_id' => $category->id, 'is_transfer' => 0])->assertRedirect('/transactions');

    expect($t->fresh()->category_id)->toBe($category->id)
        ->and($t->fresh()->updated_by)->toBe($user->id);
});

it('saves settings', function () {
    $user = member();
    $account = Account::factory()->create(['household_id' => $user->household_id]);

    $this->actingAs($user)->get('/settings')->assertOk();
    $this->get('/settings/household')->assertOk();
    $this->post('/settings', [
        'name' => 'Smith', 'period_start_day' => 25, 'own_account_names' => "J SMITH\nJ SMITH SAVINGS",
    ])->assertRedirect();
    $this->get('/settings/accounts')->assertOk();
    $this->post('/settings/accounts', ['accounts' => [$account->id => 'Our cheque account']])->assertRedirect();

    expect($user->household->fresh()->period_start_day)->toBe(25)
        ->and($user->household->fresh()->ownAccountNames())->toBe(['J SMITH', 'J SMITH SAVINGS'])
        ->and($account->fresh()->name)->toBe('Our cheque account');
});

it('keeps each household to its own data', function () {
    $mine = member();
    $theirs = member();
    $theirAccount = Account::factory()->create(['household_id' => $theirs->household_id, 'name' => 'Their account']);
    $theirTransaction = Transaction::factory()->for($theirAccount)->create(['description' => 'THEIR SECRET SHOP']);
    $theirCategory = Category::withoutGlobalScopes()->where('household_id', $theirs->household_id)->first();

    $this->actingAs($mine);
    $this->get('/transactions')->assertDontSee('Their Secret Shop');
    $this->get("/transactions/{$theirTransaction->id}")->assertNotFound();
    $this->post("/transactions/{$theirTransaction->id}", ['category_id' => null])->assertNotFound();
    $this->get('/settings/accounts')->assertDontSee('Their account');
    $this->post('/settings/accounts', ['accounts' => [$theirAccount->id => 'Hacked']]);
    $this->post('/categorise', ['merchant_key' => 'WOOLWORTHS', 'money_in' => 0, 'category' => $theirCategory->id])->assertSessionHasErrors('category');

    expect($theirAccount->fresh()->name)->toBe('Their account');
});

it('filters transactions by budget line, or those not categorised', function () {
    $user = member();
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    $groceries = Category::withoutGlobalScopes()->where('household_id', $user->household_id)->where('name', 'Groceries')->sole();
    Transaction::factory()->for($account)->create(['description' => 'WOOLWORTHS BELLVILLE', 'category_id' => $groceries->id]);
    Transaction::factory()->for($account)->create(['description' => 'TAKEALOT', 'category_id' => null]);

    $this->actingAs($user)->get("/transactions?category={$groceries->id}")
        ->assertSee('Woolworths Bellville')->assertDontSee('Takealot')->assertSee('All categories')
        ->assertSee('1 transaction')->assertSee('-R123.45');
    $this->get('/transactions?category=none')->assertSee('Takealot')->assertDontSee('Woolworths Bellville');
});

it('filters transactions by budget month', function () {
    $user = member();
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    Transaction::factory()->for($account)->create(['posted_on' => '2026-07-10', 'description' => 'JULY SHOP']);
    Transaction::factory()->for($account)->create(['posted_on' => '2026-08-10', 'description' => 'AUGUST SHOP']);

    $this->actingAs($user)->get('/transactions?month=2026-07-01')
        ->assertSee('July Shop')->assertDontSee('August Shop')
        ->assertSee('July 2026')->assertSee('August 2026')->assertSee('All months');

    // A household whose budget month starts on the 25th.
    $user->household->update(['period_start_day' => 25]);
    $this->get('/transactions?month=2026-07-25')->assertSee('August Shop')->assertDontSee('July Shop');
});
