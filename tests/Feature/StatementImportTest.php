<?php

use App\Enums\TransactionKind;
use App\Models\Account;
use App\Models\Category;
use App\Models\StatementImport;
use App\Models\Transaction;

function upload($test, string $fixture)
{
    return $test->post('/statements/preview', ['text' => fixtureText($fixture)]);
}

it('shows what a statement holds before saving anything', function () {
    $this->actingAs(member());

    upload($this, 'standard-bank')
        ->assertOk()
        ->assertSee('Standard Bank')
        ->assertSee('All 6 transactions add up')
        ->assertSee('Import 6 transactions');

    expect(Transaction::count())->toBe(0);
});

it('imports a checked statement', function () {
    $user = member(['own_account_names' => 'J SMITH']);
    $this->actingAs($user);

    upload($this, 'standard-bank');
    $this->post('/statements')->assertRedirect('/categorise');

    $account = Account::sole();
    expect($account->number_ending)->toBe('5678')
        ->and($account->statement_balance_cents)->toBe(984050)
        ->and(Transaction::count())->toBe(6)
        ->and(StatementImport::sole()->added)->toBe(6);

    $byDescription = Transaction::all()->keyBy('description');
    expect($byDescription['J SMITH DISCOVERY BA']->is_transfer)->toBeTrue()
        ->and($byDescription['HEALTHCO PREM 0001234-99887766']->kind)->toBe(TransactionKind::DebitOrder)
        ->and($byDescription['VASTE MAANDELIKSE FOOI']->category->name)->toBe('Bank fees')
        ->and($byDescription['*****1111111 10H34 *****2222']->is_transfer)->toBeTrue()
        ->and($byDescription['ACME CONSULTING INV 0012']->kind)->toBe(TransactionKind::Deposit);
});

it('pairs a card repayment with the payment it received', function () {
    $this->actingAs(member(['own_account_names' => 'J SMITH']));

    foreach (['standard-bank', 'discovery'] as $fixture) {
        upload($this, $fixture);
        $this->post('/statements');
    }

    $out = Transaction::where('description', 'J SMITH DISCOVERY BA')->sole();
    $in = Transaction::where('description', 'J SMITH')->sole();
    expect($out->transfer_pair_id)->toBe($in->id)
        ->and($in->transfer_pair_id)->toBe($out->id);
});

it('refuses the same statement twice', function () {
    $this->actingAs(member());
    upload($this, 'discovery');
    $this->post('/statements');

    upload($this, 'discovery')->assertSee('already imported');
    expect(Transaction::count())->toBe(8);
});

it('skips lines already saved from an overlapping statement', function () {
    $user = member();
    $this->actingAs($user);
    upload($this, 'discovery');
    $this->post('/statements');

    // The next statement repeats the last two lines and adds one.
    $json = json_decode(fixtureText('discovery'), true);
    $json['pages'][0] = [$json['pages'][0][0], $json['pages'][0][1], $json['pages'][1][0]];
    $json['pages'][1][] = ['y' => 730, 'items' => [
        ['x' => 61.3, 's' => '2026-07-28'], ['x' => 118.6, 's' => 'TAKEALOT'], ['x' => 383.6, 's' => 'R 91.33'], ['x' => 490.4, 's' => 'R 7,400.00'],
    ]];
    $json['pages'][1] = array_values(array_filter($json['pages'][1], fn ($r) => ! in_array($r['items'][0]['s'], ['2026-07-24'], true)));
    array_walk_recursive($json, function (&$v) {
        $v = $v === '2026-07-01' ? '2026-07-25' : $v;
    });

    $this->post('/statements/preview', ['text' => json_encode($json)])->assertSee('2 of them are already in Budgeteer');
    $this->post('/statements');

    expect(Transaction::count())->toBe(9)
        ->and(StatementImport::latest('id')->first()->already_there)->toBe(2);
});

it('explains why a statement cannot be read', function () {
    $this->actingAs(member());
    $json = json_decode(fixtureText('standard-bank'), true);
    array_walk_recursive($json, function (&$v) {
        $v = $v === '-500.00' ? '-501.00' : $v;
    });

    $this->from('/statements')->post('/statements/preview', ['text' => json_encode($json)])
        ->assertRedirect('/statements')
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'Line 1'));

    $this->from('/statements')->post('/statements/preview', ['text' => 'not json'])->assertSessionHas('error');
});

it('applies a remembered category to the next statement', function () {
    $user = member();
    $this->actingAs($user);
    upload($this, 'discovery');
    $this->post('/statements');
    $groceries = Category::where('name', 'Groceries')->sole();

    $this->post('/categorise', ['merchant_key' => 'WOOLWORTHS', 'money_in' => 0, 'category' => $groceries->id])->assertSessionHasNoErrors();

    $woolworths = Transaction::where('description', 'WOOLWORTHS BELLVILLE')->sole();
    expect($woolworths->category_id)->toBe($groceries->id);
    // The refund is money in, so it is its own group and still waits for a choice.
    expect(Transaction::where('description', 'Refund WOOLWORTHS BELLVILLE')->sole()->category_id)->toBeNull();

    upload($this, 'standard-bank');
    $this->post('/statements');
    $json = json_decode(fixtureText('discovery'), true);
    array_walk_recursive($json, function (&$v) {
        $v = match ($v) {
            '2026-07-01' => '2026-08-01', '2026-07-31' => '2026-08-31', default => $v,
        };
    });
    $json['pages'][0][0]['items'][5]['s'] = '2026-08-31';
    $this->post('/statements/preview', ['text' => json_encode($json)]);
    $this->post('/statements');

    expect(Transaction::where('description', 'WOOLWORTHS BELLVILLE')->whereNull('category_id')->count())->toBe(0);
});

it('marks a group as money moved between own accounts', function () {
    $this->actingAs(member());
    upload($this, 'discovery');
    $this->post('/statements');

    $this->post('/categorise', ['merchant_key' => 'SMITH', 'money_in' => 1, 'category' => 'transfer']);

    expect(Transaction::where('description', 'J SMITH')->sole()->is_transfer)->toBeTrue();
});
