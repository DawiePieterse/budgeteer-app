<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\Project;
use App\Models\Transaction;

it('keeps a project out of the monthly budget and tracks it on its own page', function () {
    $user = member();
    $this->actingAs($user);
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    $motors = Transaction::factory()->for($account)->create(['posted_on' => today(), 'amount_cents' => -2000000, 'description' => '291636028 GRAND SLAM MOTORS', 'merchant_key' => 'GRAND SLAM']);
    Transaction::factory()->for($account)->create(['posted_on' => today(), 'amount_cents' => -12300, 'description' => 'WOOLWORTHS']);

    $this->post('/projects', ['name' => 'Shrek', 'budget' => '100000'])->assertRedirect();
    $shrek = Project::sole();
    $this->post('/categorise', ['merchant_key' => 'GRAND SLAM', 'money_in' => 0, 'category' => 'project:'.$shrek->id])
        ->assertSessionHas('status', fn ($s) => str_contains($s, 'Shrek'));

    expect($motors->fresh()->project_id)->toBe($shrek->id)
        ->and(Merchant::where('key', 'GRAND SLAM')->sole()->project_id)->toBe($shrek->id);
    // Spending shows only the R123; the R20,000 appears only against the project.
    $this->get('/?in='.today()->toDateString())->assertSee('R123.00')->assertSee('Shrek')->assertSee('R20,000.00 ›', false);
    expect(substr_count($this->get('/?in='.today()->toDateString())->getContent(), 'R20,000.00'))->toBe(1);
    $this->get("/projects/{$shrek->id}")->assertOk()->assertSee('R20,000.00')->assertSee('20%')->assertSee('R80,000.00 left');
    $this->get('/categorise')->assertDontSee('GRAND SLAM');
});

it('sends later payments to the same place into the project', function () {
    $user = member();
    $this->actingAs($user);
    $shrek = Project::create(['name' => 'Shrek']);
    Merchant::create(['key' => 'ACME', 'project_id' => $shrek->id]);

    $this->post('/statements/preview', ['text' => fixtureText('standard-bank')]);
    $this->post('/statements');

    expect(Transaction::where('description', 'ACME CONSULTING INV 0012')->sole()->project_id)->toBe($shrek->id);

    $this->post("/projects/{$shrek->id}/forget/ACME");
    expect(Merchant::where('key', 'ACME')->sole()->project_id)->toBeNull();
});

it('moves a single transaction into a project', function () {
    $user = member();
    $this->actingAs($user);
    $shrek = Project::create(['name' => 'Shrek']);
    $t = Transaction::factory()->for(Account::factory()->create(['household_id' => $user->household_id]))->create();

    $this->post("/transactions/{$t->id}", ['project_id' => $shrek->id, 'is_transfer' => 0])->assertRedirect();

    expect($t->fresh()->project_id)->toBe($shrek->id);
    $this->get('/transactions')->assertSee('Project: Shrek');
});

it('keeps projects to their own household', function () {
    $theirs = member();
    $project = Project::withoutGlobalScopes()->create(['household_id' => $theirs->household_id, 'name' => 'Theirs']);

    $this->actingAs(member());
    $this->get("/projects/{$project->id}")->assertNotFound();
    $this->post('/categorise', ['merchant_key' => 'X', 'money_in' => 0, 'category' => 'project:'.$project->id])->assertSessionHasErrors('category');
});

it('draws each budget line as a meter against 100% of its budget', function () {
    $user = member();
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    $food = Category::withoutGlobalScopes()->where('household_id', $user->household_id)->where('name', 'Groceries')->sole();
    $food->update(['budget_cents' => 1000000]);
    Transaction::factory()->for($account)->create(['posted_on' => '2026-07-03', 'amount_cents' => -1142500, 'category_id' => $food->id]);

    $this->actingAs($user)->get('/?in=2026-07-15')
        ->assertSee('114%')
        ->assertSee('R1,425.00 over')
        ->assertSee('Groceries: R11,425.00 of R10,000.00 (114%), R1,425.00 over')   // the chart's text alternative
        ->assertSee('width="100"', false);                                          // fill capped at the full budget
});
