@extends('layouts.app')

@section('title', 'Household · Budgeteer')

@section('content')
    <x-back :href="route('settings')" label="More" />
    <header class="page-head"><h1>Household</h1></header>

    <form method="POST" action="{{ route('settings.update') }}" class="card pad">
        @csrf
        <div class="field">
            <label for="name">Name</label>
            <input type="text" name="name" id="name" value="{{ old('name', $household->name) }}" required maxlength="100">
        </div>
        <div class="field">
            <label for="period_start_day">The budget month starts on day</label>
            <input type="number" name="period_start_day" id="period_start_day" min="1" max="28" value="{{ old('period_start_day', $household->period_start_day) }}" required>
            @if ($household->keep_from)
                <span class="hint">Budgeteer keeps data from {{ $household->keep_from->format('j F Y') }}; anything older is skipped.</span>
            @endif
        </div>
        <div class="field">
            <label for="own_account_names">Names on payments between your own accounts</label>
            <textarea name="own_account_names" id="own_account_names" rows="3" placeholder="J SMITH">{{ old('own_account_names', $household->own_account_names) }}</textarea>
            <span class="hint">One per line, as the bank shows it. A payment whose description starts with one of these (for example the credit card repayment) is moved money, not spending or income. Applies to statements imported from now on.</span>
        </div>
        <button type="submit">Save</button>
    </form>

    <section class="section" aria-labelledby="users-h">
        <div class="section-head"><h2 id="users-h">Who can sign in</h2></div>
        <div class="card">
            @foreach ($users as $user)
                <div class="item">
                    <span class="avatar" aria-hidden="true">@if ($user->avatar_url)<img src="{{ $user->avatar_url }}" alt="">@else{{ mb_substr($user->name, 0, 1) }}@endif</span>
                    <span class="item-main"><span class="item-title">{{ $user->name }}</span><span class="item-sub">{{ $user->email }}</span></span>
                </div>
            @endforeach
        </div>
        <p class="section-note">Add someone with <code>php artisan budgeteer:setup --emails=…</code> on the server.</p>
    </section>
@endsection
