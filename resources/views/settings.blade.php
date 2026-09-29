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

            <p class="small"><a href="{{ route('budget') }}">Budget and categories ›</a></p>

            <label for="period_start_day">Budget month starts on day</label>
            <input type="number" name="period_start_day" id="period_start_day" min="1" max="28" value="{{ old('period_start_day', $household->period_start_day) }}" required>

            @if ($household->keep_from)
                <p class="muted small">Budgeteer keeps data from {{ $household->keep_from->format('j F Y') }}; anything older is skipped.</p>
            @endif

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

    <section class="card" id="push" data-key="{{ config('budgeteer.push.public_key') }}" data-store="{{ route('push.store') }}" data-destroy="{{ route('push.destroy') }}">
        <h2>Phone notifications</h2>
        <p class="small" data-push-status role="status">Checking this phone…</p>
        <p class="small" data-push-ios hidden>On iPhone: open this page in Safari, tap <strong>Share</strong> then <strong>Add to Home Screen</strong>. Open Budgeteer from the new icon, come back to Settings and turn notifications on.</p>
        <button type="button" data-push-on hidden>Turn on for this phone</button>
        <button type="button" class="secondary" data-push-off hidden>Turn off for this phone</button>

        <form method="POST" action="{{ route('push.preferences') }}">
            @csrf
            <p class="small">Tell me when</p>
            <label class="check"><input type="checkbox" name="notify_recurring" value="1" @checked($me->notify_recurring)> a recurring payment is late or its amount changed</label>
            <label class="check"><input type="checkbox" name="notify_budget" value="1" @checked($me->notify_budget)> a budget line reaches {{ (int) (\App\Notify\NoticeFinder::WARN_AT * 100) }}% or goes over, or the whole budget goes over</label>
            <label class="check"><input type="checkbox" name="notify_gmail" value="1" @checked($me->notify_gmail)> the bank emails stop coming in</label>
            <label class="check"><input type="checkbox" name="notify_summary" value="1" @checked($me->notify_summary)> a budget month ends: how it went</label>
            <label class="check"><input type="checkbox" name="notify_statements" value="1" @checked($me->notify_statements)> a new bank statement should be out and is not uploaded yet</label>
            <button type="submit" class="secondary">Save choices</button>
        </form>

        @if ($devices->isNotEmpty())
            <p class="small">Your phones and browsers</p>
            @foreach ($devices as $device)
                <div class="row">
                    <span>{{ $device->device ?? 'Phone' }} <span class="muted small block">Added {{ $device->created_at->format('j M Y') }}@if ($device->last_sent_at) · last notified {{ $device->last_sent_at->diffForHumans() }}@endif</span></span>
                    <form method="POST" action="{{ route('push.remove', $device) }}">
                        @csrf
                        <button type="submit" class="link">Remove</button>
                    </form>
                </div>
            @endforeach
            <form method="POST" action="{{ route('push.test') }}">
                @csrf
                <button type="submit" class="secondary">Send a test notification</button>
            </form>
        @endif
        <p class="muted small">Sent between 07:00 and 20:30, each warning once. Each person chooses for themselves.</p>
    </section>

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
                <li>In Gmail on a computer, search for <code>from:(discovery OR standardbank) subject:("Transaction update" OR MyUpdates)</code>.</li>
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

    @if ($people->isNotEmpty())
        <section class="card">
            <h2>People who pay you back</h2>
            @foreach ($people as $person)
                <a class="row" href="{{ route('people.show', $person) }}">
                    <span>{{ $person->name }}</span>
                    <span class="small">{{ $owed[$person->id] === 0 ? 'All square' : money($owed[$person->id]) }} ›</span>
                </a>
            @endforeach
            <p class="muted small">Add someone by choosing <strong>Bought for someone new…</strong> on a purchase.</p>
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

@push('scripts')
    <script src="{{ versioned_asset('js/push.js') }}" defer></script>
@endpush
