<?php

use App\Services\BudgetList;

it('reads a budget pasted from a spreadsheet, with tabs, emoji and a header', function () {
    $pasted = "\t\tBedrag\t\n"
        ."Everyday food items & household basics\t\t10000\t\n"
        ."Levies Vygeboom\t\t2807.44\t\n"
        ."🍽️ Eating Out & Takeaways\t\t2000\t\n"
        ."🧑‍🔧 Personal Care\t\t1000\t\n"
        ."Monthly bank charges\t\t96.25\t\n"
        ."Oortrekking diensgeld\t\t57.5\t\n"
        ."Afrihost internet and cellphones   R1,200\n"
        ."Something with a space thousand  2 807,44\n"
        ."A line without an amount\n";

    expect(BudgetList::parse($pasted))->toBe([
        ['name' => 'Everyday food items & household basics', 'cents' => 1000000, 'group' => null],
        ['name' => 'Levies Vygeboom', 'cents' => 280744, 'group' => null],
        ['name' => 'Eating Out & Takeaways', 'cents' => 200000, 'group' => null],
        ['name' => 'Personal Care', 'cents' => 100000, 'group' => null],
        ['name' => 'Monthly bank charges', 'cents' => 9625, 'group' => null],
        ['name' => 'Oortrekking diensgeld', 'cents' => 5750, 'group' => null],
        ['name' => 'Afrihost internet and cellphones', 'cents' => 120000, 'group' => null],
        ['name' => 'Something with a space thousand', 'cents' => 280744, 'group' => null],
    ]);
});

it('puts lines under a heading without an amount in that group', function () {
    $pasted = "Item\tAmount\n"
        ."Before any heading\t100\n"
        ."🏠 Home and bills\n"
        ."Levies\t2807.44\n"
        ."Electricity\t1800\n"
        ."\n"
        ."Food\t\t\n"
        ."Groceries\t10000\n"
        ."Total\t14707.44\n"
        ."A heading with nothing under it\n";

    expect(BudgetList::parse($pasted))->toBe([
        ['name' => 'Before any heading', 'cents' => 10000, 'group' => null],
        ['name' => 'Levies', 'cents' => 280744, 'group' => 'Home and bills'],
        ['name' => 'Electricity', 'cents' => 180000, 'group' => 'Home and bills'], // a blank line keeps the group
        ['name' => 'Groceries', 'cents' => 1000000, 'group' => 'Food'],
    ]);
});
