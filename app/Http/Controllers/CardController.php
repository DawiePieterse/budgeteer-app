<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Person;
use App\Services\PersonBalance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CardController extends Controller
{
    public const HOUSEHOLD = 'household';

    public const NEW_PERSON = 'new';

    /** Whose spending the card is: the household's budget, or charged to a person. */
    public function update(Request $request, Card $card, PersonBalance $balances): RedirectResponse
    {
        $data = $request->validate([
            'owner' => ['required', Rule::in([self::HOUSEHOLD, self::NEW_PERSON, ...Person::query()->pluck('id')->map(fn ($id) => (string) $id)->all()])],
            'new_person' => ['required_if:owner,'.self::NEW_PERSON, 'nullable', 'string', 'max:100'],
        ]);

        $person = match ($data['owner']) {
            self::HOUSEHOLD => null,
            self::NEW_PERSON => Person::create(['name' => trim((string) $data['new_person']), 'opening_balance_on' => now()->toDateString()]),
            default => Person::findOrFail((int) $data['owner']),
        };

        $card->update(['charge_to_person_id' => $person?->id]);
        $count = $balances->applyCard($card);

        if ($person === null) {
            return back()->with('status', "Card ••{$card->number_ending} counts in our budget again ({$count} transactions).");
        }

        return redirect()->route('people.show', $person)->with('status', "Card ••{$card->number_ending} is charged to {$person->name}. Check what {$person->name} owed on the day it starts from.");
    }
}
