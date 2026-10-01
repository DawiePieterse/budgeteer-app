@extends('layouts.app')

@section('title', 'Accounts and cards · Budgeteer')

@section('content')
    <x-back :href="route('settings')" label="More" />
    <header class="page-head"><h1>Accounts and cards</h1></header>

    @if ($accounts->isEmpty())
        <div class="card"><p class="empty">Accounts appear here once a statement or bank email is in. <a href="{{ route('statements.index') }}">Add a statement</a>.</p></div>
    @else
        <form method="POST" action="{{ route('settings.accounts.update') }}" class="section">
            @csrf
            <div class="section-head"><h2>Account names</h2></div>
            <div class="card pad">
                @foreach ($accounts as $account)
                    <div class="field">
                        <label for="account-{{ $account->id }}">{{ $account->bank->label() }} ••{{ $account->number_ending }}</label>
                        <input type="text" name="accounts[{{ $account->id }}]" id="account-{{ $account->id }}" value="{{ old('accounts.'.$account->id, $account->name) }}" required maxlength="100">
                    </div>
                @endforeach
                <button type="submit">Save names</button>
            </div>
        </form>
    @endif

    @if ($cards->isNotEmpty())
        <section class="section" aria-labelledby="cards-h">
            <div class="section-head"><h2 id="cards-h">Cards</h2></div>
            <p class="section-note">A card charged to someone is kept out of the budget: what is bought on it is owed to you.</p>
            @foreach ($cards as $card)
                <form method="POST" action="{{ route('cards.update', $card) }}" class="card pad">
                    @csrf
                    <div class="group-head">
                        <span class="tile"><x-icon name="card" /></span>
                        <span class="item-main">
                            <span class="item-title">••{{ $card->number_ending }} · {{ $card->holder_name ?? 'Main cardholder' }}</span>
                            <span class="item-sub">{{ $card->account->name }} ••{{ $card->account->number_ending }}@if ($card->chargeToPerson) · <a href="{{ route('people.show', $card->chargeToPerson) }}">{{ $card->chargeToPerson->name }} owes</a>@endif</span>
                        </span>
                    </div>
                    <div class="field">
                        <label for="owner-{{ $card->id }}">Whose spending</label>
                        <select name="owner" id="owner-{{ $card->id }}" data-reveal="{{ \App\Http\Controllers\CardController::NEW_PERSON }}" data-reveal-target="new-person-{{ $card->id }}">
                            <option value="{{ \App\Http\Controllers\CardController::HOUSEHOLD }}" @selected($card->charge_to_person_id === null)>Our budget</option>
                            @foreach ($people as $person)
                                <option value="{{ $person->id }}" @selected($card->charge_to_person_id === $person->id)>Charge to {{ $person->name }}</option>
                            @endforeach
                            <option value="{{ \App\Http\Controllers\CardController::NEW_PERSON }}">Charge to someone new…</option>
                        </select>
                    </div>
                    <div class="field" id="new-person-{{ $card->id }}">
                        <label for="new-person-name-{{ $card->id }}">Their name</label>
                        <input type="text" name="new_person" id="new-person-name-{{ $card->id }}" value="{{ $card->charge_to_person_id === null && $card->holder_name ? $card->holder_name : '' }}" maxlength="100">
                    </div>
                    <button type="submit" class="secondary">Save</button>
                </form>
            @endforeach
        </section>
    @endif
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/reveal.js') }}" defer></script>
@endpush
