<?php

use App\Models\Household;
use App\Models\User;
use App\Services\HouseholdSetup;
use App\Statements\StatementText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Unit');

function fixtureText(string $name): string
{
    return (string) file_get_contents(__DIR__.'/Fixtures/statements/'.$name.'.json');
}

function fixtureStatement(string $name): StatementText
{
    return StatementText::fromArray(json_decode(fixtureText($name), true));
}

/** A signed-in member of a household with the default categories. */
function member(array $household = []): User
{
    $h = Household::factory()->create($household);
    app(HouseholdSetup::class)->createDefaultCategories($h);

    return User::factory()->create(['household_id' => $h->id]);
}
