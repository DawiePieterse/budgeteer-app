@extends('layouts.app')

@section('title', 'Settings · Budgeteer')

@section('content')
    <h1>Settings</h1>

    <form method="POST" action="{{ route('settings.update') }}">
        @csrf
        <section class="card">
            <h2>Household</h2>
            <label for="name">Name</label>
            <input type="text" name="name" id="name" value="{{ old('name', $household->name) }}" required maxlength="100">

            <label for="period_start_day">Budget month starts on day</label>
            <input type="number" name="period_start_day" id="period_start_day" min="1" max="28" value="{{ old('period_start_day', $household->period_start_day) }}" required>

            <label for="own_account_names">Names on payments between your own accounts</label>
            <textarea name="own_account_names" id="own_account_names" rows="3" placeholder="J SMITH">{{ old('own_account_names', $household->own_account_names) }}</textarea>
            <p class="muted small">One per line, as the bank shows it. A payment whose description starts with one of these (for example the credit card repayment) is moved money, not spending or income. Applies to statements imported from now on.</p>
        </section>

        @if ($accounts->isNotEmpty())
            <section class="card">
                <h2>Accounts</h2>
                @foreach ($accounts as $account)
                    <label for="account-{{ $account->id }}">{{ $account->bank->label() }} ••{{ $account->number_ending }}</label>
                    <input type="text" name="accounts[{{ $account->id }}]" id="account-{{ $account->id }}" value="{{ old('accounts.'.$account->id, $account->name) }}" required maxlength="100">
                @endforeach
            </section>
        @endif

        <button type="submit">Save settings</button>
    </form>

    <section class="card" id="gmail">
        <h2>Bank emails (Gmail)</h2>
        @forelse ($connections as $connection)
            <div class="row">
                <span>
                    {{ $connection->email }}
                    <span class="muted small block">
                        @switch($connection->status)
                            @case(\App\Models\GmailConnection::ACTIVE)
                                Reading emails labelled <strong>{{ \App\Models\GmailConnection::LABEL }}</strong>@if ($connection->last_synced_at) · checked {{ $connection->last_synced_at->diffForHumans() }}@endif
                                @break
                            @case(\App\Models\GmailConnection::LABEL_MISSING)
                                There is no Gmail label called <strong>{{ \App\Models\GmailConnection::LABEL }}</strong> yet. Create the filter below.
                                @break
                            @case(\App\Models\GmailConnection::NEEDS_RELINK)
                                Google no longer allows reading this Gmail. Link it again.
                                @break
                            @default
                                Last check failed: {{ $connection->last_error }}
                        @endswitch
                    </span>
                </span>
                <span class="inline actions">
                    <form method="POST" action="{{ route('gmail.sync', $connection) }}">
                        @csrf
                        <button type="submit" class="secondary">Check now</button>
                    </form>
                    <form method="POST" action="{{ route('gmail.destroy', $connection) }}">
                        @csrf
                        <button type="submit" class="link">Unlink</button>
                    </form>
                </span>
            </div>
        @empty
            <p class="muted small">Link the Gmail account that receives the bank's notification emails. Budgeteer can only read, never send or delete, and only reads emails with the <strong>{{ \App\Models\GmailConnection::LABEL }}</strong> label.</p>
        @endforelse
        <a class="button @if ($connections->isNotEmpty()) secondary @endif" href="{{ route('gmail.link') }}">{{ $connections->isEmpty() ? 'Link Gmail' : 'Link again or add another' }}</a>

        <details class="small">
            <summary>How to label the bank emails</summary>
            <ol>
                <li>In Gmail on a computer, search for <code>from:discovery subject:"Transaction update"</code>.</li>
                <li>Click the filter icon in the search box, then <strong>Create filter</strong>.</li>
                <li>Tick <strong>Apply the label</strong>, choose <strong>New label…</strong>, name it <code>{{ \App\Models\GmailConnection::LABEL }}</code>.</li>
                <li>Tick <strong>Also apply filter to matching conversations</strong> and click <strong>Create filter</strong>.</li>
            </ol>
        </details>
    </section>

    @if ($cards->isNotEmpty())
        <section class="card">
            <h2>Cards</h2>
            <p class="muted small">A card charged to someone is kept out of the budget: what is bought on it is owed to you.</p>
            @foreach ($cards as $card)
                <form method="POST" action="{{ route('cards.update', $card) }}" class="row">
                    @csrf
                    <span>
                        ••{{ $card->number_ending }} · {{ $card->holder_name ?? 'Main cardholder' }}
                        <span class="muted small block">{{ $card->account->name }} ••{{ $card->account->number_ending }}@if ($card->chargeToPerson) · <a href="{{ route('people.show', $card->chargeToPerson) }}">{{ $card->chargeToPerson->name }} owes</a>@endif</span>
                    </span>
                    <span class="inline actions">
                        <label class="visually-hidden" for="owner-{{ $card->id }}">Whose spending</label>
                        <select name="owner" id="owner-{{ $card->id }}">
                            <option value="{{ \App\Http\Controllers\CardController::HOUSEHOLD }}" @selected($card->charge_to_person_id === null)>Our budget</option>
                            @foreach ($people as $person)
                                <option value="{{ $person->id }}" @selected($card->charge_to_person_id === $person->id)>Charge to {{ $person->name }}</option>
                            @endforeach
                            <option value="{{ \App\Http\Controllers\CardController::NEW_PERSON }}">Charge to someone new…</option>
                        </select>
                        <label class="visually-hidden" for="new-person-{{ $card->id }}">Name</label>
                        <input type="text" name="new_person" id="new-person-{{ $card->id }}" placeholder="Name, if new" value="{{ $card->charge_to_person_id === null && $card->holder_name ? $card->holder_name : '' }}" maxlength="100">
                        <button type="submit" class="secondary">Save</button>
                    </span>
                </form>
            @endforeach
        </section>
    @endif

    @if ($emails->isNotEmpty())
        <section class="card">
            <h2>Latest bank emails</h2>
            @foreach ($emails as $email)
                <div class="row">
                    <span>
                        {{ $email->transaction?->description ?? $email->subject }}
                        <span class="muted small block">{{ $email->received_at?->format('j M H:i') }} · {{ $email->sender }}@if ($email->note) · {{ $email->note }}@endif</span>
                    </span>
                    <span class="small">
                        @switch($email->status)
                            @case('added') Added @break
                            @case('matched') On a statement @break
                            @case('ignored') Skipped @break
                            @case('unrecognised') Not read @break
                            @default Failed
                        @endswitch
                        @if ($email->transaction) <span class="amount block">{{ money($email->transaction->amount_cents) }}</span> @endif
                    </span>
                </div>
            @endforeach
        </section>
    @endif

    <section class="card">
        <h2>Who can sign in</h2>
        @foreach ($users as $user)
            <div class="row"><span>{{ $user->name }} <span class="muted small block">{{ $user->email }}</span></span></div>
        @endforeach
        <p class="muted small">Add someone with <code>php artisan budgeteer:setup --emails=…</code> on the server.</p>
    </section>
@endsection
