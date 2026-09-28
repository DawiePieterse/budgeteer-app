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

    <section class="card">
        <h2>Who can sign in</h2>
        @foreach ($users as $user)
            <div class="row"><span>{{ $user->name }} <span class="muted small block">{{ $user->email }}</span></span></div>
        @endforeach
        <p class="muted small">Add someone with <code>php artisan budgeteer:setup --emails=…</code> on the server.</p>
    </section>
@endsection
