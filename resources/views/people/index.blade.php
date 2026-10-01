@extends('layouts.app')

@section('title', 'People who pay you back · Budgeteer')

@section('content')
    <x-back :href="route('settings')" label="More" />
    <header class="page-head"><h1>People who pay you back</h1></header>

    @if ($people->isNotEmpty())
        <div class="card">
            @foreach ($people as $person)
                <a class="item owner-{{ $person->ownerColour() }}" href="{{ route('people.show', $person) }}">
                    <span class="avatar owned" aria-hidden="true">{{ mb_substr($person->name, 0, 1) }}</span>
                    <span class="item-main"><span class="item-title">{{ $person->name }}</span><span class="item-sub">{{ $owed[$person->id] === 0 ? 'All square' : ($owed[$person->id] > 0 ? 'Owes us' : 'We owe them') }}</span></span>
                    @if ($owed[$person->id] !== 0)<span class="amount">{{ money(abs($owed[$person->id])) }}</span>@endif
                    <x-icon name="forward" class="chev" :size="20" />
                </a>
            @endforeach
        </div>
    @endif
    <p class="section-note">Add someone by choosing <strong>Bought for someone new…</strong> on a purchase, or <strong>Charge to someone new…</strong> for a card in Accounts and cards.</p>
@endsection
