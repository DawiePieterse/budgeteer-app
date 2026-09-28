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
        ['name' => 'Everyday food items & household basics', 'cents' => 1000000],
        ['name' => 'Levies Vygeboom', 'cents' => 280744],
        ['name' => 'Eating Out & Takeaways', 'cents' => 200000],
        ['name' => 'Personal Care', 'cents' => 100000],
        ['name' => 'Monthly bank charges', 'cents' => 9625],
        ['name' => 'Oortrekking diensgeld', 'cents' => 5750],
        ['name' => 'Afrihost internet and cellphones', 'cents' => 120000],
        ['name' => 'Something with a space thousand', 'cents' => 280744],
    ]);
});
