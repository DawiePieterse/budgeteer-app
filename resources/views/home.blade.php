@extends('layouts.app')

@section('content')
    @php($budgetLines = collect($spending)->filter(fn ($r) => ($r['budget'] ?? null) !== null))
    @php($otherLines = collect($spending)->filter(fn ($r) => ($r['budget'] ?? null) === null))
    @php($month = $period->from->toDateString())

    <header class="page-head">
        <div class="page-head-row">
            <div>
                <p class="eyebrow">{{ auth()->user()->household->name }}</p>
                <h1>{{ $when }}</h1>
            </div>
            @include('partials.period', ['route' => 'home', 'class' => 'head-period'])
            <a href="{{ route('settings') }}" class="avatar avatar-link" aria-label="More and settings">{{ mb_substr(auth()->user()->name, 0, 1) }}</a>
        </div>
    </header>

    <div class="home-grid">
        <div class="home-top span-7">
            @include('partials.period', ['route' => 'home'])

            <section class="card hero" aria-labelledby="hero-label">
                @if ($budgeted > 0)
                    @php($left = $budgeted - $spentOnLines)
                    <div class="hero-top">
                        <div class="hero-text">
                            <h2 class="hero-label" id="hero-label">{{ $left >= 0 ? 'Left to spend' : 'Over budget' }}</h2>
                            <p @class(['hero-value', 'over' => $left < 0])>{{ rand_whole(abs($left)) }}</p>
                            <p class="hero-note">of {{ rand_whole($budgeted) }} budget @if ($daysLeft !== null) · {{ $daysLeft === 0 ? 'last day' : $daysLeft.' '.($daysLeft === 1 ? 'day' : 'days').' to go' }} @endif</p>
                        </div>
                        <x-budget-ring :spent="$spentOnLines" :budget="$budgeted" />
                    </div>
                @else
                    <div class="hero-text">
                        <h2 class="hero-label" id="hero-label">Spent</h2>
                        <p class="hero-value">{{ rand_whole($spent) }}</p>
                        <p class="hero-note"><a href="{{ route('budget') }}">Set a budget</a> to see what is left to spend.</p>
                    </div>
                @endif
                <dl class="stats">
                    <div><dt>Money in</dt><dd class="in">{{ $received > 0 ? '+' : '' }}{{ rand_whole($received) }}</dd></div>
                    <div><dt>Spent</dt><dd>{{ rand_whole($spent) }}</dd></div>
                    <div><dt>In − spent</dt><dd @class(['in' => $received >= $spent, 'out' => $received < $spent])>{{ $received > $spent ? '+' : '' }}{{ rand_whole($received - $spent) }}</dd></div>
                </dl>
            </section>
        </div>

        @php($attention = collect($recurring)->filter->needsAttention())
        @php($paid = collect($recurring)->whereIn('status', ['paid', 'paid_by_hand'])->count())
        <section class="section span-5" aria-labelledby="attention-h">
            <div class="section-head"><h2 id="attention-h">Needs attention</h2></div>
            <div class="card">
                @if ($toCategorise > 0)
                    <a class="item" href="{{ route('categorise') }}">
                        <span class="tile accent"><x-icon name="inbox" /></span>
                        <span class="item-main"><span class="item-title">{{ $toCategorise }} {{ $toCategorise === 1 ? 'transaction' : 'transactions' }} to review</span><span class="item-sub">Grouped by shop, biggest first</span></span>
                        <x-icon name="forward" class="chev" :size="20" />
                    </a>
                @endif
                @foreach ($attention as $o)
                    <a class="item" href="{{ route('recurring.index', ['in' => $month]) }}">
                        <span class="tile warn"><x-icon :name="$o->status === 'late' ? 'clock' : 'alert'" /></span>
                        <span class="item-main">
                            <span class="item-title">{{ $o->payment->name }} {{ $o->status === 'late' ? 'is late' : 'amount changed' }}</span>
                            <span class="item-sub">@if ($o->transaction) {{ money(abs($o->transaction->amount_cents)) }} taken, expected {{ money($o->payment->amount_cents) }} @else {{ money($o->payment->amount_cents) }} due {{ $o->dueOn->format('j M') }}, not seen yet @endif</span>
                        </span>
                        <x-icon name="forward" class="chev" :size="20" />
                    </a>
                @endforeach
                @foreach ($statementsDue as $due)
                    <a class="item" href="{{ route('statements.index') }}">
                        <span class="tile"><x-icon name="upload" /></span>
                        <span class="item-main"><span class="item-title">{{ $due['account']->bank->label() }} statement</span><span class="item-sub">{{ $due['account']->name }} ••{{ $due['account']->number_ending }}: the one to {{ $due['expected']->format('j M') }} should be out</span></span>
                        <x-icon name="forward" class="chev" :size="20" />
                    </a>
                @endforeach
                @if ($recurring === [])
                    <a class="item" href="{{ route('recurring.index') }}">
                        <span class="tile"><x-icon name="repeat" /></span>
                        <span class="item-main"><span class="item-title">Set up recurring payments</span><span class="item-sub">Debit orders, levies and the like, to hear when one is late or changes</span></span>
                        <x-icon name="forward" class="chev" :size="20" />
                    </a>
                @elseif ($toCategorise === 0 && $attention->isEmpty() && $statementsDue === [])
                    <div class="item">
                        <span class="tile ok"><x-icon name="check" /></span>
                        <span class="item-main"><span class="item-title">All clear</span><span class="item-sub">{{ $paid }} of {{ count($recurring) }} recurring payments paid this month</span></span>
                    </div>
                @endif
            </div>
        </section>

        @if ($budgeted > 0)
            @php($lineLink = fn ($row) => route('transactions.index', ['category' => $row['id'], 'month' => $month]))
            <section class="section span-12" aria-labelledby="budget-h">
                <div class="section-head">
                    <div class="head-start"><h2 id="budget-h">Budget</h2><a href="{{ route('budget') }}">Edit</a></div>
                    <nav class="view-switch" aria-label="Show the budget as">
                        <a href="{{ route('home', ['in' => $month, 'view' => 'circles']) }}" @if ($budgetView === 'circles') aria-current="true" @endif>Circles</a>
                        <a href="{{ route('home', ['in' => $month, 'view' => 'list']) }}" @if ($budgetView === 'list') aria-current="true" @endif>List</a>
                    </nav>
                </div>
                <div class="card budget-card">
                    @if ($budgetView === 'circles')
                        {{-- In budget-list order, so each line keeps its place. --}}
                        <div class="circles">
                            @foreach ($budgetLines->sortBy('sort') as $row)
                                <x-budget-circle :id="'c'.$row['id']" :spent="$row['cents']" :budget="$row['budget']" :label="$row['name']" :icon="$row['icon']" :href="$lineLink($row)" />
                            @endforeach
                            @if ($otherLines->sum('cents') > 0)
                                <x-budget-circle id="other" :spent="(int) $otherLines->sum('cents')" label="Other" icon="tag" :href="route('transactions.index', ['month' => $month])" />
                            @endif
                        </div>
                    @else
                        {{-- Lines over budget or at least 80% used show; the rest are one tap away. --}}
                        @php($watch = $budgetLines->filter(fn ($r) => $r['budget'] > 0 ? $r['cents'] / $r['budget'] >= 0.8 : $r['cents'] > 0))
                        @php($onTrack = $budgetLines->diffKeys($watch))
                        <div class="lines">
                            <x-budget-line class="total" :spent="$spentOnLines" :budget="$budgeted" label="All budget lines" />
                            @foreach ($watch as $row)
                                <x-budget-line :spent="$row['cents']" :budget="$row['budget']" :label="$row['name']" :icon="$row['icon']" :href="$lineLink($row)" />
                            @endforeach
                            @if ($onTrack->isNotEmpty())
                                <details class="more-lines" @if ($watch->isEmpty()) open @endif>
                                    <summary>{{ $watch->isEmpty() ? 'All' : 'Show' }} {{ $onTrack->count() }} {{ $watch->isEmpty() ? '' : 'more ' }}on track</summary>
                                    <div class="lines">
                                        @foreach ($onTrack as $row)
                                            <x-budget-line :spent="$row['cents']" :budget="$row['budget']" :label="$row['name']" :icon="$row['icon']" :href="$lineLink($row)" />
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        </div>
                    @endif
                </div>
            </section>
        @endif

        <div class="home-side span-4">
            <section class="section" aria-labelledby="other-h">
                <div class="section-head">
                    <h2 id="other-h">{{ $budgeted > 0 ? 'Not in the budget' : 'Spending' }}</h2>
                    @if ($budgeted === 0)<a href="{{ route('budget') }}">Set a budget</a>@endif
                </div>
                <div class="card">
                    @forelse ($otherLines as $row)
                        <a class="item tight" href="{{ $row['id'] ? route('transactions.index', ['category' => $row['id'], 'month' => $month]) : route('categorise') }}">
                            <span class="tile sm"><x-category-icon :icon="$row['icon']" :size="20" /></span>
                            <span class="item-main"><span class="item-title">{{ $row['name'] }}</span></span>
                            <span class="amount">{{ money($row['cents']) }}</span>
                        </a>
                    @empty
                        <p class="empty">@if ($budgeted > 0) Everything spent is in a budget line. @else Nothing spent in this period yet. <a href="{{ route('statements.index') }}">Add a statement</a>. @endif</p>
                    @endforelse
                </div>
            </section>

            @if ($projects->isNotEmpty())
                <section class="section" aria-labelledby="projects-h">
                    <div class="section-head"><h2 id="projects-h">Special projects</h2></div>
                    <div class="card">
                        @foreach ($projects as $project)
                            @php($projectSpent = $project->spentCents())
                            <a class="item {{ 'owner-'.$project->ownerColour() }}" href="{{ route('projects.show', $project) }}">
                                <span class="tile owned"><x-icon name="folder" /></span>
                                <span class="item-main">
                                    <span class="item-title-row"><span class="item-title">{{ $project->name }}</span><span class="amount">{{ money($projectSpent) }}</span></span>
                                    @if ($project->budget_cents)
                                        <x-bar :share="$projectSpent / $project->budget_cents" :over="$projectSpent > $project->budget_cents" />
                                        <span class="item-sub">{{ (int) round($projectSpent / $project->budget_cents * 100) }}% of {{ money($project->budget_cents) }}</span>
                                    @else
                                        <span class="item-sub">Outside the monthly budget</span>
                                    @endif
                                </span>
                                <x-icon name="forward" class="chev" :size="20" />
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        <div class="home-side span-4">
            @if ($income !== [])
                <section class="section" aria-labelledby="in-h">
                    <div class="section-head"><h2 id="in-h">Money in</h2></div>
                    <div class="card">
                        @foreach ($income as $row)
                            <div class="item tight">
                                <span class="tile sm ok"><x-icon name="money" :size="20" /></span>
                                <span class="item-main"><span class="item-title">{{ $row['name'] }}</span></span>
                                <span class="amount in">{{ money($row['cents']) }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($owedToUs->isNotEmpty())
                <section class="section" aria-labelledby="owed-h">
                    <div class="section-head"><h2 id="owed-h">Owed to us</h2><span class="muted">{{ money($owedToUs->sum('cents')) }}</span></div>
                    <div class="card">
                        @foreach ($owedToUs as $row)
                            <a class="item owner-{{ $row['person']->ownerColour() }}" href="{{ route('people.show', $row['person']) }}">
                                <span class="avatar owned" aria-hidden="true">{{ mb_substr($row['person']->name, 0, 1) }}</span>
                                <span class="item-main"><span class="item-title">{{ $row['person']->name }}</span><span class="item-sub">{{ $row['cents'] > 0 ? 'Owes us' : 'We owe them' }}</span></span>
                                <span class="amount">{{ money(abs($row['cents'])) }}</span>
                                <x-icon name="forward" class="chev" :size="20" />
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        @if ($accounts->isNotEmpty())
            <section class="section home-side-last span-4" aria-labelledby="accounts-h">
                <div class="section-head"><h2 id="accounts-h">Accounts</h2></div>
                <div class="card">
                    @foreach ($accounts as $account)
                        <div class="item">
                            <span class="tile" aria-hidden="true">{{ $account->bank->initials() }}</span>
                            <span class="item-main"><span class="item-title">{{ $account->name }}</span><span class="item-sub">{{ $account->bank->label() }} ••{{ $account->number_ending }}</span></span>
                            @if ($account->statement_balance_cents !== null)
                                <span class="item-end"><span class="amount">{{ money($account->statement_balance_cents) }}</span><span class="item-sub">on {{ $account->statement_balance_on->format('j M') }}</span></span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
