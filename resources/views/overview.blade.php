@extends('layouts.app')

@section('title', 'Overview · Budgeteer')

@section('content')
    @php($month = $period->from->toDateString())
    @php($left = $summary->budgeted - $summary->spentOnLines())
    @php($net = $summary->received() - $summary->spent())
    @php($soFar = $isCurrent ? ' by now' : '')

    <div class="overview">
        <header class="ov-head">
            <div>
                <p class="ov-eyebrow">{{ $period->from->format('j M') }} – {{ $period->to->format('j M Y') }}@if ($daysLeft !== null) · {{ $daysLeft === 0 ? 'last day' : $daysLeft.' '.($daysLeft === 1 ? 'day' : 'days').' to go' }}@endif</p>
                <h1>Overview</h1>
            </div>
            <nav class="ov-stepper" aria-label="Budget month">
                <a href="{{ route('overview', ['in' => $period->previous()->from->toDateString()]) }}" aria-label="Previous month"><x-icon name="back" :size="18" /></a>
                <span>{{ $monthName }}@if (! $period->to->isSameYear(today())) {{ $period->to->year }}@endif</span>
                <a href="{{ route('overview', ['in' => $period->next()->from->toDateString()]) }}" aria-label="Next month"><x-icon name="forward" :size="18" /></a>
            </nav>
        </header>

        <div class="ov-grid">
            <section class="ov-card ov-kpi c3" aria-labelledby="kpi-in">
                <h2 class="ov-label" id="kpi-in"><span class="ov-dot in" aria-hidden="true"></span>Money in</h2>
                <p class="ov-value">{{ rand_whole($summary->received()) }}</p>
                @include('partials.overview-change', ['change' => $inChange, 'more' => 'more', 'less' => 'less'])
            </section>

            <section class="ov-card ov-kpi c3" aria-labelledby="kpi-out">
                <h2 class="ov-label" id="kpi-out"><span class="ov-dot out" aria-hidden="true"></span>{{ $isCurrent ? 'Spent so far' : 'Spent' }}</h2>
                <p class="ov-value">{{ rand_whole($summary->spent()) }}</p>
                @include('partials.overview-change', ['change' => $outChange, 'more' => 'more', 'less' => 'less'])
            </section>

            <section class="ov-card ov-kpi c3" aria-labelledby="kpi-left">
                @if ($summary->budgeted > 0)
                    <h2 class="ov-label" id="kpi-left"><span class="ov-dot left" aria-hidden="true"></span>{{ $left >= 0 ? 'Left to spend' : 'Over budget' }}</h2>
                    <p @class(['ov-value', 'over' => $left < 0])>{{ rand_whole(abs($left)) }}</p>
                    <div class="ov-foot">
                        <span class="ov-note">of {{ rand_whole($summary->budgeted) }}@if ($daysLeft !== null && $left > 0) · {{ rand_whole(intdiv($left, $daysLeft + 1)) }} a day @endif</span>
                        <x-overview-ring :spent="$summary->spentOnLines()" :budget="$summary->budgeted" />
                    </div>
                @else
                    <h2 class="ov-label" id="kpi-left"><span class="ov-dot left" aria-hidden="true"></span>Left to spend</h2>
                    <p class="ov-value muted">–</p>
                    <p class="ov-note"><a href="{{ route('budget') }}">Set a budget</a> to see what is left.</p>
                @endif
            </section>

            <section class="ov-card ov-kpi c3" aria-labelledby="kpi-net">
                <h2 class="ov-label" id="kpi-net"><span class="ov-dot net" aria-hidden="true"></span>In − spent</h2>
                <p @class(['ov-value', 'over' => $net < 0])>{{ $net > 0 ? '+' : '' }}{{ rand_whole($net) }}</p>
                <p class="ov-note">Money in less all spending{{ $soFar }}</p>
            </section>

            <section class="ov-card c8" id="lines" aria-labelledby="lines-h">
                <div class="ov-card-head">
                    <div><h2 id="lines-h">Budget lines</h2><p class="ov-sub">{{ $isCurrent ? 'This month so far' : $monthName }}</p></div>
                    @if (count($summary->groups) > 0)
                        <nav class="ov-seg" aria-label="Show a group">
                            @php($ungrouped = collect($summary->spending)->contains(fn ($row) => $row['group'] === null))
                            @php($groupLinks = [['', 'All'], ...array_map(fn ($g) => [(string) $g->id, $g->name], $summary->groups), ...($ungrouped ? [['none', 'Other']] : [])])
                            @foreach ($groupLinks as [$id, $label])
                                <a href="{{ route('overview', array_filter(['in' => $isCurrent ? null : $month, 'group' => $id])) }}#lines" @if ((string) $groupId === $id) aria-current="true" @endif>{{ $label }}</a>
                            @endforeach
                        </nav>
                    @endif
                </div>
                <div class="ov-table-wrap">
                    <table class="ov-table">
                        <thead>
                            <tr><th scope="col">Line</th><th scope="col" class="num">Budget</th><th scope="col" class="num">Spent</th><th scope="col" class="num">Left</th><th scope="col" class="used"><span class="visually-hidden">Share of the budget used</span></th><th scope="col" class="num status">Recurring</th></tr>
                        </thead>
                        <tbody>
                            @php($colours = collect($summary->groups)->mapWithKeys(fn ($g) => [$g->id => $g->colour]))
                            @forelse ($lines as $line)
                                @php($lineLeft = $line['budget'] !== null ? $line['budget'] - $line['cents'] : null)
                                @php($status = $line['id'] !== null ? ($statuses[$line['id']] ?? null) : null)
                                @php($colour = $line['group'] !== null ? $colours->get($line['group']) : null)
                                <tr @class(['group-colour-'.$colour => $colour !== null])>
                                    <th scope="row">
                                        <a class="ov-line" href="{{ route('transactions.index', ['category' => $line['id'] ?? 'none', 'month' => $month]) }}"><span @class(['group-dot', 'none' => $colour === null]) aria-hidden="true"></span>{{ $line['name'] }}</a>
                                    </th>
                                    <td class="num">{{ $line['budget'] !== null ? rand_whole($line['budget']) : '–' }}</td>
                                    <td class="num">{{ rand_whole($line['cents']) }}</td>
                                    <td @class(['num', 'strong', 'out' => $lineLeft !== null && $lineLeft < 0, 'in' => $lineLeft !== null && $lineLeft > 0])>{{ $lineLeft === null ? '–' : ($lineLeft < 0 ? rand_whole(-$lineLeft).' over' : rand_whole($lineLeft)) }}</td>
                                    <td class="used">@if ($line['budget'])<x-bar :share="$line['cents'] / $line['budget']" :over="$line['cents'] > $line['budget']" />@endif</td>
                                    <td class="num status"><span @class(['ov-status', $status['tone'] ?? 'muted'])>{{ $status['text'] ?? '' }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="ov-empty">Nothing here {{ $isCurrent ? 'yet this month' : 'in '.$monthName }}.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="ov-card c4" aria-labelledby="bvr-h">
                <div class="ov-card-head">
                    <div><h2 id="bvr-h">Budget vs real</h2><p class="ov-sub">{{ count($summary->groups) > 0 ? 'By group' : 'The biggest lines' }}</p></div>
                </div>
                <div class="ov-bars">
                    @foreach ($groupTotals as $total)
                        <div @class(['ov-bar-row', 'group-colour-'.$total['colour'] => $total['colour'] !== null])>
                            <div class="ov-bar-text">
                                <span class="ov-bar-name">{{ $total['name'] }}</span>
                                <span class="ov-bar-amount"><strong>{{ rand_whole($total['cents']) }}</strong> @if ($total['budget'] > 0)<span>of {{ rand_whole($total['budget']) }}</span>@else<span>no budget</span>@endif</span>
                            </div>
                            <x-bar class="lg" :share="$total['budget'] > 0 ? $total['cents'] / $total['budget'] : ($total['cents'] > 0 ? 1 : 0)" :over="$total['budget'] > 0 && $total['cents'] > $total['budget']" />
                        </div>
                    @endforeach
                    <div class="ov-bar-row total">
                        <div class="ov-bar-text">
                            <span class="ov-bar-name">All spending</span>
                            <span class="ov-bar-amount"><strong>{{ rand_whole($summary->spent()) }}</strong> @if ($summary->budgeted > 0)<span>of {{ rand_whole($summary->budgeted) }}</span>@endif</span>
                        </div>
                        <x-bar class="lg" :share="$summary->budgeted > 0 ? $summary->spent() / $summary->budgeted : 0" :over="$summary->budgeted > 0 && $summary->spent() > $summary->budgeted" />
                    </div>
                </div>
                @if (count($summary->groups) === 0)
                    <p class="ov-note ov-hint"><a href="{{ route('budget') }}#groups-h">Make groups</a> of your budget lines to compare them here.</p>
                @endif
            </section>
        </div>
    </div>
@endsection
