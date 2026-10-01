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
    $this->get('/?in='.today()->toDateString())->assertSee('R123.00')->assertSee('Shrek')->assertSee('<span class="amount">R20,000.00</span>', false);
    expect(substr_count($this->get('/?in='.today()->toDateString())->getContent(), 'R20,000.00'))->toBe(1);
    $this->get("/projects/{$shrek->id}")->assertOk()->assertSee('R20,000.00')->assertSee('20%')->assertSee('R80,000.00 left');
    $this->get('/categorise')->assertDontSee('Grand Slam');
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
    $this->get('/transactions')->assertSee('<span class="who">Shrek</span>', false);
});

it('keeps projects to their own household', function () {
    $theirs = member();
    $project = Project::withoutGlobalScopes()->create(['household_id' => $theirs->household_id, 'name' => 'Theirs']);

    $this->actingAs(member());
    $this->get("/projects/{$project->id}")->assertNotFound();
    $this->post('/categorise', ['merchant_key' => 'X', 'money_in' => 0, 'category' => 'project:'.$project->id])->assertSessionHasErrors('category');
});

it('shows each budget line as spent of budget, with what is over and the percentage', function () {
    $user = member();
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    $food = Category::withoutGlobalScopes()->where('household_id', $user->household_id)->where('name', 'Groceries')->sole();
    $food->update(['budget_cents' => 1000000]);
    Transaction::factory()->for($account)->create(['posted_on' => '2026-07-03', 'amount_cents' => -1142500, 'category_id' => $food->id]);

    $this->actingAs($user)->get('/?in=2026-07-15&view=list')
        ->assertSee('114%')
        ->assertSee('R1,425.00 over')
        ->assertSee('Groceries: R11,425.00 of R10,000.00 (114%), R1,425.00 over')   // read out by screen readers
        ->assertSee('of R10,000.00')
        ->assertDontSee('class="circles"', false);
});

it('shows budget lines as circles filled with the share used, in budget order, and remembers the view', function () {
    $user = member();
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    $line = fn (string $name, int $budget) => tap(Category::withoutGlobalScopes()->where('household_id', $user->household_id)->where('name', $name)->sole())->update(['budget_cents' => $budget]);
    $food = $line('Groceries', 500000);
    $fuel = $line('Fuel and car', 200000);
    $line('Medical', 900000);
    Transaction::factory()->for($account)->create(['posted_on' => '2026-07-03', 'amount_cents' => -420000, 'category_id' => $food->id]);
    Transaction::factory()->for($account)->create(['posted_on' => '2026-07-04', 'amount_cents' => -230000, 'category_id' => $fuel->id]);
    Transaction::factory()->for($account)->create(['posted_on' => '2026-07-05', 'amount_cents' => -5000, 'description' => 'NEW SHOP']);
    $this->actingAs($user);

    $page = $this->get('/?in=2026-07-15')->assertOk()
        ->assertSee('class="circles"', false)
        ->assertSee('aria-label="All budget lines: R6,500.00 of R16,000.00 spent, R9,500.00 left"', false)
        ->assertSee('Groceries: R4,200.00 of R5,000.00 spent, R800.00 left')
        ->assertSee('Fuel and car: R2,300.00 of R2,000.00 spent, R300.00 over')
        ->assertSeeInOrder(['R300', 'over'])                                   // under the circle, whole rand
        ->assertSee('Medical: R0.00 of R9,000.00 spent, R9,000.00 left')     // nothing spent yet: still shown
        ->assertSee('Other: R50.00 spent, not on a budget line')
        ->assertSee('y="10.3"', false);                                         // 84% used: the fill's top edge high in the circle
    expect(strpos($page->getContent(), 'Groceries: R4,200') < strpos($page->getContent(), 'Fuel and car: R2,300'))->toBeTrue(); // list order, not fullest first

    $this->get('/?in=2026-07-15&view=list')->assertCookie('budget_view', 'list')->assertDontSee('class="circles"', false);
    $this->withCookie('budget_view', 'list')->get('/?in=2026-07-15')->assertDontSee('class="circles"', false);
});

it('guesses an icon from the line name, and lets it be chosen', function () {
    $user = member();
    $food = Category::withoutGlobalScopes()->where('household_id', $user->household_id)->where('name', 'Groceries')->sole();
    expect($food->iconName())->toBe('basket');

    $this->actingAs($user)->post('/budget', ['name' => [$food->id => 'Groceries'], 'budget' => [$food->id => '5000'], 'icon' => [$food->id => 'cart']])->assertSessionHasNoErrors();
    expect($food->fresh()->iconName())->toBe('cart');
    $this->post('/budget', ['name' => [$food->id => 'Groceries'], 'icon' => [$food->id => 'rocket']])->assertSessionHasErrors('icon.'.$food->id);
    $this->post('/budget', ['name' => [$food->id => 'Groceries'], 'icon' => [$food->id => '']]);
    expect($food->fresh()->icon)->toBeNull();
});

it('removes a special project only once it has no payments left', function () {
    $user = member();
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    $kombi = Project::create(['household_id' => $user->household_id, 'name' => 'Kombi']);
    $payment = Transaction::factory()->for($account)->create(['project_id' => $kombi->id, 'merchant_key' => 'MIDAS']);
    Merchant::create(['household_id' => $user->household_id, 'key' => 'MIDAS', 'project_id' => $kombi->id]);
    $this->actingAs($user);

    $this->get("/projects/{$kombi->id}")->assertSee('Kombi still has 1 payment')->assertDontSee('Remove Kombi');
    $this->post("/projects/{$kombi->id}/delete")->assertSessionHas('error', fn ($e) => str_contains($e, 'still has 1 payment'));
    expect(Project::find($kombi->id))->not->toBeNull();

    // Moved back into the monthly budget on its own page, the project is empty and can go.
    $this->post("/transactions/{$payment->id}", ['category_id' => '', 'person_id' => '', 'project_id' => '', 'is_transfer' => 0])->assertSessionHasNoErrors();
    $this->get("/projects/{$kombi->id}")->assertSee('Remove Kombi')->assertSee('Payments at Midas will no longer go to a project');
    $this->followingRedirects()->post("/projects/{$kombi->id}/delete")
        ->assertSee('Project Kombi removed.')->assertDontSee('href="'.route('projects.show', $kombi).'"', false);

    expect(Project::find($kombi->id))->toBeNull()
        ->and(Merchant::where('key', 'MIDAS')->sole()->project_id)->toBeNull()   // its shops no longer go there
        ->and($payment->fresh()->project_id)->toBeNull();
    $this->get('/projects')->assertDontSee('Kombi');
    $this->get('/')->assertDontSee('Kombi');
});

it('does not let one household remove another household\'s project', function () {
    $mine = member();
    $theirs = member();
    $theirProject = Project::withoutGlobalScopes()->create(['household_id' => $theirs->household_id, 'name' => 'Their project']);

    $this->actingAs($mine)->post("/projects/{$theirProject->id}/delete")->assertNotFound();
    expect(Project::withoutGlobalScopes()->find($theirProject->id))->not->toBeNull();
});
