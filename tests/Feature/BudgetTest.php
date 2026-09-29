<?php

use App\Enums\TransactionKind;
use App\Http\Controllers\CategoriseController;
use App\Models\Account;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\Project;
use App\Models\Transaction;
use App\Services\HouseholdSetup;

it('turns a pasted budget into categories with monthly amounts', function () {
    $this->actingAs($user = member());

    $this->post('/budget/paste', ['list' => "Everyday food items & household basics\t10000\nPetrol & tolls\t4000\nBank fees\t96.25"])
        ->assertSessionHas('status', fn ($s) => str_contains($s, '3 budget lines read (2 new categories)'));

    expect(Category::where('name', 'Petrol & tolls')->sole()->budget_cents)->toBe(400000)
        ->and(Category::where('name', 'Bank fees')->sole()->budget_cents)->toBe(9625) // existing category, now with a budget
        ->and(Category::count())->toBe(count(HouseholdSetup::EXPENSE_CATEGORIES) + count(HouseholdSetup::INCOME_CATEGORIES) + 2);
    $this->get('/budget')->assertOk()->assertSee('Everyday food items &amp; household basics', false)->assertSee('R14,096.25');
});

it('shows each budget line with what is spent of it, and what is left', function () {
    $user = member();
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    $food = Category::withoutGlobalScopes()->where('household_id', $user->household_id)->where('name', 'Groceries')->sole();
    $food->update(['budget_cents' => 1000000]);
    Category::withoutGlobalScopes()->where('household_id', $user->household_id)->where('name', 'Medical')->update(['budget_cents' => 877200]);
    Transaction::factory()->for($account)->create(['posted_on' => '2026-07-03', 'amount_cents' => -1142500, 'category_id' => $food->id]);

    $this->actingAs($user)->get('/?in=2026-07-15')
        ->assertSee('R11,425.00')
        ->assertSee('of R10,000.00')
        ->assertSee('of R8,772.00')       // a budget line with nothing spent yet
        ->assertSee('Left to spend')
        ->assertSee('R7,347.00');         // R18,772 budget less R11,425 spent
});

it('moves one category into another, with what it remembers', function () {
    $user = member();
    $this->actingAs($user);
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    $groceries = Category::where('name', 'Groceries')->sole();
    $food = Category::create(['name' => 'Everyday food', 'kind' => 'expense', 'budget_cents' => 1000000]);
    $t = Transaction::factory()->for($account)->create(['category_id' => $groceries->id]);
    Merchant::create(['key' => 'WOOLWORTHS', 'category_id' => $groceries->id]);

    $this->post('/budget/merge', ['from' => $groceries->id, 'into' => $food->id])->assertSessionHasNoErrors();

    expect(Category::where('name', 'Groceries')->exists())->toBeFalse()
        ->and($t->fresh()->category_id)->toBe($food->id)
        ->and(Merchant::where('key', 'WOOLWORTHS')->sole()->category_id)->toBe($food->id);
});

it('keeps filing bank fees in the category the starter one was moved into', function () {
    $user = member(['own_account_names' => 'J SMITH']);
    $this->actingAs($user);
    $charges = Category::create(['name' => 'Monthly bank charges', 'kind' => 'expense']);
    $this->post('/budget/merge', ['from' => Category::where('name', 'Bank fees')->sole()->id, 'into' => $charges->id]);

    $this->post('/statements/preview', ['text' => fixtureText('standard-bank')]);
    $this->post('/statements');

    expect(Transaction::where('kind', TransactionKind::Fee)->sole()->category_id)->toBe($charges->id);
});

it('removes categories nobody uses', function () {
    $user = member();
    $this->actingAs($user);
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    Transaction::factory()->for($account)->create(['category_id' => Category::where('name', 'Groceries')->sole()->id]);
    Category::where('name', 'Medical')->update(['budget_cents' => 100]);

    $this->post('/budget/tidy');

    expect(Category::pluck('name')->sort()->values()->all())->toBe(['Bank fees', 'Cash', 'Groceries', 'Interest', 'Medical']);
});

it('saves amounts and names, and adds a category', function () {
    $this->actingAs(member());
    $groceries = Category::where('name', 'Groceries')->sole();

    $this->post('/budget', [
        'name' => [$groceries->id => 'Food'], 'budget' => [$groceries->id => '9500.50'],
        'new_name' => 'Netflix', 'new_budget' => '300', 'new_kind' => 'expense',
    ])->assertSessionHasNoErrors();

    expect($groceries->fresh()->name)->toBe('Food')
        ->and($groceries->fresh()->budget_cents)->toBe(950050)
        ->and(Category::where('name', 'Netflix')->sole()->budget_cents)->toBe(30000);
});

it('keeps budgets to their own household', function () {
    $mine = member();
    $theirs = member();
    $theirCategory = Category::withoutGlobalScopes()->where('household_id', $theirs->household_id)->first();

    $this->actingAs($mine);
    $this->post('/budget', ['name' => [$theirCategory->id => 'Hacked'], 'budget' => [$theirCategory->id => 1]]);
    $this->post('/budget/merge', ['from' => $theirCategory->id, 'into' => Category::first()->id])->assertSessionHasErrors('from');

    expect($theirCategory->fresh()->name)->not->toBe('Hacked');
});

it('starts the spending categories again from a pasted budget, keeping transactions', function () {
    $user = member();
    $this->actingAs($user);
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    $groceries = Category::where('name', 'Groceries')->sole();
    $consulting = Category::where('name', 'Consulting')->sole();
    $spend = Transaction::factory()->for($account)->create(['category_id' => $groceries->id]);
    $income = Transaction::factory()->for($account)->create(['amount_cents' => 500000, 'category_id' => $consulting->id]);
    Merchant::create(['key' => 'WOOLWORTHS', 'category_id' => $groceries->id]);
    $project = Project::create(['name' => 'Shrek']);
    Merchant::create(['key' => 'GRAND SLAM', 'project_id' => $project->id]);

    $this->post('/budget/reset', ['list' => "Everyday food\t10000\nPPS (life cover / risk component)\t6000"])->assertSessionHasErrors('confirm');
    $this->post('/budget/reset', ['list' => "Everyday food\t10000\nPPS (life cover / risk component)\t6000", 'confirm' => '1'])->assertRedirect('/categorise');

    expect(Category::where('kind', 'expense')->pluck('name')->all())->toBe(['Everyday food', 'PPS (life cover / risk component)'])
        ->and(Category::where('kind', 'income')->count())->toBe(4)
        ->and(Transaction::count())->toBe(2)
        ->and($spend->fresh()->category_id)->toBeNull()
        ->and($income->fresh()->category_id)->toBe($consulting->id)
        ->and(Merchant::where('key', 'WOOLWORTHS')->exists())->toBeFalse()
        ->and(Merchant::where('key', 'GRAND SLAM')->sole()->project_id)->toBe($project->id);
});

it('suggests the budget line whose name matches the merchant', function () {
    $user = member();
    $this->actingAs($user);
    $cats = collect([
        Category::create(['name' => 'PPS (life cover / risk component)', 'kind' => 'expense']),
        Category::create(['name' => 'Afrihost internet and cellphones', 'kind' => 'expense']),
        Category::create(['name' => 'Church (NG Kerk)', 'kind' => 'expense']),
        Category::create(['name' => 'AutoGen', 'kind' => 'expense']),
        Category::create(['name' => 'Everyday food items & household basics', 'kind' => 'expense']),
    ]);

    $suggest = fn (string $key, string $example = '') => CategoriseController::suggest($key, $example, $cats);
    expect($suggest('PPS', 'PPS 12345 0XYU3D'))->toBe($cats[0]->id)
        ->and($suggest('AFRIHOST', 'AFRIHOST.COM'))->toBe($cats[1]->id)
        ->and($suggest('NG KERK', 'NG KERK LANGEBAAN DANKOFFER'))->toBe($cats[2]->id)
        ->and($suggest('AUTOGENVAPPAG', 'AUTOGENVAPPAG1234 MAY 2026'))->toBe($cats[3]->id)
        ->and($suggest('WOOLWORTHS', 'WOOLWORTHS BELLVILLE'))->toBeNull();
});
