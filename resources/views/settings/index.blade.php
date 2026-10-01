@extends('layouts.app')

@section('title', 'More · Budgeteer')

@section('content')
    <header class="page-head"><h1>More</h1></header>

    <section class="card" aria-label="Signed in">
        <div class="item">
            <span class="avatar lg" aria-hidden="true">@if ($me->avatar_url)<img src="{{ $me->avatar_url }}" alt="">@else{{ mb_substr($me->name, 0, 1) }}@endif</span>
            <span class="item-main"><span class="item-title">{{ $me->name }}</span><span class="item-sub">{{ $household->name }} · signed in with Google</span></span>
        </div>
    </section>

    @php
        $owing = $owed->filter(fn ($row) => $row['cents'] > 0);
        $gmailProblem = $connections->first(fn ($c) => $c->status !== \App\Models\GmailConnection::ACTIVE);
        $lastChecked = $connections->max('last_synced_at');
        $money = [
            [route('statements.index'), 'upload', 'Statements',
                $statementsDue === [] ? 'Add a PDF statement from the bank' : $statementsDue[0]['account']->bank->label().' statement due'.(count($statementsDue) > 1 ? ' and '.(count($statementsDue) - 1).' more' : ''),
                $statementsDue !== []],
            [route('recurring.index'), 'repeat', 'Recurring payments',
                $recurringAttention > 0 ? $recurringAttention.' '.($recurringAttention === 1 ? 'needs' : 'need').' attention' : ($recurringCount > 0 ? $recurringCount.' this month, nothing unusual' : 'Debit orders and other payments that come back'),
                $recurringAttention > 0],
            [route('people.index'), 'people', 'People who pay you back',
                $owing->isNotEmpty() ? $owing->map(fn ($row) => $row['person']->name.' owes '.money($row['cents']))->join(', ') : ($owed->isNotEmpty() ? 'All square' : 'Nobody yet'),
                false],
            [route('projects.index'), 'folder', 'Special projects', $projects->isNotEmpty() ? $projects->join(', ') : 'Spending kept out of the monthly budget', false],
        ];
        $settings = [
            [route('settings.household'), 'home', 'Household', $household->name.' · the budget month starts on day '.$household->period_start_day.' · '.$users.' '.($users === 1 ? 'person signs' : 'people sign').' in', false],
            [route('settings.accounts'), 'card', 'Accounts and cards', $accountCount.' '.($accountCount === 1 ? 'account' : 'accounts').($chargedCards > 0 ? ' · '.$chargedCards.' '.($chargedCards === 1 ? 'card' : 'cards').' charged to someone' : ''), false],
            [route('settings.gmail'), 'mail', 'Bank emails',
                $connections->isEmpty() ? 'Gmail is not linked yet' : ($gmailProblem ? 'Gmail needs attention' : 'Gmail linked'.($lastChecked ? ' · checked '.$lastChecked->diffForHumans() : '')),
                $gmailProblem !== null],
            [route('settings.notifications'), 'bell', 'Phone notifications', $devices > 0 ? 'On for '.$devices.' '.($devices === 1 ? 'phone or browser' : 'phones or browsers') : 'Not on for any phone yet', false],
        ];
    @endphp

    @foreach (['Money' => $money, 'Settings' => $settings] as $title => $rows)
        <section class="section" aria-labelledby="group-{{ $loop->index }}">
            <div class="section-head"><h2 id="group-{{ $loop->index }}">{{ $title }}</h2></div>
            <div class="card">
                @foreach ($rows as [$href, $icon, $label, $sub, $warn])
                    <a class="item" href="{{ $href }}">
                        <span @class(['tile', 'sm', 'warn' => $warn])><x-icon :name="$icon" :size="20" /></span>
                        <span class="item-main"><span class="item-title">{{ $label }}</span><span @class(['item-sub', 'status warn' => $warn])>{{ $sub }}</span></span>
                        <x-icon name="forward" class="chev" :size="20" />
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach

    <div class="card">
        <a class="item tight" href="{{ route('privacy') }}">
            <span class="tile sm"><x-icon name="shield" :size="20" /></span>
            <span class="item-main"><span class="item-title">Privacy</span></span>
            <x-icon name="forward" class="chev" :size="20" />
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="item item-button tight">
                <span class="tile sm danger"><x-icon name="signout" :size="20" /></span>
                <span class="item-main"><span class="item-title out">Sign out</span></span>
            </button>
        </form>
    </div>
@endsection
