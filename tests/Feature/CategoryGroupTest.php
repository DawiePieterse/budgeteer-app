<?php

use App\Models\Category;
use App\Models\CategoryGroup;

it('adds groups, each with the next free colour, last in the order', function () {
    $this->actingAs(member());

    $this->post('/budget/groups', ['new_group' => 'Home and bills'])->assertSessionHas('status', fn ($s) => str_contains($s, 'Group "Home and bills" added'));
    $this->post('/budget/groups', ['new_group' => 'Car']);

    expect(CategoryGroup::orderBy('sort')->get(['name', 'colour'])->toArray())->toBe([
        ['name' => 'Home and bills', 'colour' => 'indigo'],
        ['name' => 'Car', 'colour' => 'orange'],
    ]);
});

it('puts a budget line in a group with the budget Save', function () {
    $this->actingAs(member());
    $car = CategoryGroup::create(['name' => 'Car']);
    $fuel = Category::where('name', 'Fuel and car')->sole();
    $groceries = Category::where('name', 'Groceries')->sole();

    $this->post('/budget', [
        'name' => [$fuel->id => 'Fuel and car', $groceries->id => 'Groceries'],
        'budget' => [$fuel->id => '3500', $groceries->id => ''],
        'group' => [$fuel->id => (string) $car->id, $groceries->id => ''],
    ])->assertSessionHasNoErrors();

    expect($fuel->fresh()->category_group_id)->toBe($car->id)
        ->and($groceries->fresh()->category_group_id)->toBeNull();
});

it('refuses another household\'s group', function () {
    $theirs = member();
    $this->actingAs($theirs);
    $group = CategoryGroup::create(['name' => 'Theirs']);

    $this->actingAs(member());
    $fuel = Category::where('name', 'Fuel and car')->sole();

    $this->post('/budget', ['name' => [$fuel->id => 'Fuel and car'], 'group' => [$fuel->id => (string) $group->id]])
        ->assertSessionHasErrors('group.'.$fuel->id);
    expect($fuel->fresh()->category_group_id)->toBeNull();
});

it('renames and recolours groups, and refuses the same name twice', function () {
    $this->actingAs(member());
    $home = CategoryGroup::create(['name' => 'Home']);
    $car = CategoryGroup::create(['name' => 'Car']);

    $this->post('/budget/groups', ['group_name' => [$home->id => 'Home and bills', $car->id => 'Car'], 'group_colour' => [$home->id => 'teal', $car->id => 'orange']])
        ->assertSessionHasNoErrors();
    expect($home->fresh()->only(['name', 'colour']))->toBe(['name' => 'Home and bills', 'colour' => 'teal']);

    $this->post('/budget/groups', ['group_name' => [$home->id => 'Car', $car->id => 'Home and bills']])->assertSessionHasNoErrors(); // a swap
    expect(CategoryGroup::orderBy('sort')->pluck('name')->all())->toBe(['Car', 'Home and bills']);
    $this->post('/budget/groups', ['group_name' => [$home->id => 'Home and bills', $car->id => 'Car']]);

    $this->post('/budget/groups', ['group_name' => [$home->id => 'Car', $car->id => 'car']])->assertSessionHasErrors();
    $this->post('/budget/groups', ['group_name' => [$home->id => 'Home and bills', $car->id => 'Car'], 'new_group' => 'CAR'])->assertSessionHasErrors('new_group');
    expect(CategoryGroup::count())->toBe(2);
});

it('moves a group up, keeping changes made at the same time', function () {
    $this->actingAs(member());
    $home = CategoryGroup::create(['name' => 'Home']);
    $food = CategoryGroup::create(['name' => 'Food']);
    $car = CategoryGroup::create(['name' => 'Car']);

    $this->post('/budget/groups', ['group_name' => [$home->id => 'Home', $food->id => 'Food', $car->id => 'Car and fuel'], 'action' => 'up:'.$car->id])
        ->assertSessionHasNoErrors();

    expect(CategoryGroup::orderBy('sort')->pluck('name')->all())->toBe(['Home', 'Car and fuel', 'Food']);
});

it('removes a group and leaves its lines without one', function () {
    $this->actingAs(member());
    $car = CategoryGroup::create(['name' => 'Car']);
    $fuel = Category::where('name', 'Fuel and car')->sole();
    $fuel->update(['category_group_id' => $car->id]);

    $this->post('/budget/groups', ['group_name' => [$car->id => 'Car'], 'action' => 'remove:'.$car->id])
        ->assertSessionHas('status', fn ($s) => str_contains($s, 'Group "Car" removed'));

    expect(CategoryGroup::count())->toBe(0)
        ->and($fuel->fresh())->not->toBeNull()
        ->and($fuel->fresh()->category_group_id)->toBeNull();
});

it('makes groups from the headings in a pasted budget', function () {
    $this->actingAs(member());
    $existing = CategoryGroup::create(['name' => 'Home and bills']);
    $groceries = Category::where('name', 'Groceries')->sole();
    $medical = Category::where('name', 'Medical')->sole();
    $medical->update(['category_group_id' => $existing->id]);

    $this->post('/budget/paste', ['list' => "Medical\t1500\nHOME AND BILLS\nLevies\t2807.44\nFood\nGroceries\t10000\nEating out\t2000"])
        ->assertSessionHasNoErrors();

    $food = CategoryGroup::where('name', 'Food')->sole();
    expect(CategoryGroup::count())->toBe(2) // "HOME AND BILLS" is the group that was there
        ->and(Category::where('name', 'Levies')->sole()->category_group_id)->toBe($existing->id)
        ->and($groceries->fresh()->category_group_id)->toBe($food->id)
        ->and(Category::where('name', 'Eating out')->sole()->category_group_id)->toBe($food->id)
        ->and($medical->fresh()->category_group_id)->toBe($existing->id); // above any heading: its group stays
});

it('makes groups when starting again from a pasted budget', function () {
    $this->actingAs(member());

    $this->post('/budget/reset', ['list' => "Huis\nLevies\t2807.44\nKar\nPetrol\t4000", 'confirm' => '1'])->assertSessionHasNoErrors();

    expect(Category::where('name', 'Levies')->sole()->group->name)->toBe('Huis')
        ->and(Category::where('name', 'Petrol')->sole()->group->name)->toBe('Kar');
});

it('shows the spending lines under their groups, with each group\'s budget', function () {
    $this->actingAs(member());
    $this->get('/budget')->assertOk()->assertDontSee('group-select')->assertSee('First group');

    $car = CategoryGroup::create(['name' => 'Car']);
    Category::where('name', 'Fuel and car')->update(['category_group_id' => $car->id, 'budget_cents' => 350000]);
    Category::where('name', 'Insurance')->update(['category_group_id' => $car->id, 'budget_cents' => 120000]);

    $this->get('/budget')->assertOk()
        ->assertSeeInOrder(['Car', 'R4,700.00', 'Fuel and car', 'Insurance', 'No group', 'Groceries', 'Money in lines'])
        ->assertSee('group-colour-indigo')
        ->assertSee('name="group['.Category::where('name', 'Groceries')->sole()->id.']"', false)
        ->assertSee('Add a group');
});
