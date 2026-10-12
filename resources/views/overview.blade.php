@extends('layouts.app')

@section('title', 'Overview · Budgeteer')

@section('content')
    @php($month = $period->from->toDateString())
    @php($left = $summary->budgeted - $summary->spentOnLines())
    @php($net = $summary->received() - $summary->spent())
    @php($soFar = $isCurrent ? ' by now' : '')
    @php($hasGroups = count($summary->groups) > 0)
    {{-- This page with some of its choices changed; the others (month, range, group) are kept. --}}
    @php($here = fn (array $change = []) => route('overview', array_filter(array_merge(['in' => $isCurrent ? null : $month, 'range' => $range === '12' ? null : $range, 'group' => $groupId], $change), fn ($v) => $v !== null && $v !== '')))

    <div class="overview">
        <header class="ov-head">
            <div>
                <p class="ov-eyebrow">{{ $period->from->format('j M') }} – {{ $period->to->format('j M Y') }}@if ($daysLeft !== null) · {{ $daysLeft === 0 ? 'last day' : $daysLeft.' '.($daysLeft === 1 ? 'day' : 'days').' to go' }}@endif</p>
                <h1>Overview</h1>
            </div>
            <div class="ov-controls">
                <nav class="ov-seg" aria-label="How far back the trends reach">
                    @foreach (['6' => '6 months', '12' => '12 months', 'all' => 'All'] as $value => $label)
                        <a href="{{ $here(['range' => $value === '12' ? null : $value]) }}" @if ($range === (string) $value) aria-current="true" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
                <nav class="ov-stepper" aria-label="Budget month">
                    <a href="{{ $here(['in' => $period->previous()->from->toDateString()]) }}" aria-label="Previous month"><x-icon name="back" :size="18" /></a>
                    <span>{{ $monthName }}@if (! $period->to->isSameYear(today())) {{ $period->to->year }}@endif</span>
                    <a href="{{ $here(['in' => $period->next()->from->toDateString()]) }}" aria-label="Next month"><x-icon name="forward" :size="18" /></a>
                </nav>
            </div>
        </header>

        <div class="ov-grid">
            {{-- The month in four numbers, each with the months before it as a small line. --}}
            <section class="ov-card ov-kpi c3" aria-labelledby="kpi-in">
                <h2 class="ov-label" id="kpi-in"><span class="ov-dot in" aria-hidden="true"></span>Money in</h2>
                <p class="ov-value">{{ rand_whole($summary->received()) }}</p>
                <div class="ov-foot">
                    @include('partials.overview-change', ['change' => $inChange, 'more' => 'more', 'less' => 'less'])
                    <x-chart.spark class="in" :values="$sparks['in']" :width="72" />
                </div>
            </section>

            <section class="ov-card ov-kpi c3" aria-labelledby="kpi-out">
                <h2 class="ov-label" id="kpi-out"><span class="ov-dot out" aria-hidden="true"></span>{{ $isCurrent ? 'Spent so far' : 'Spent' }}</h2>
                <p class="ov-value">{{ rand_whole($summary->spent()) }}</p>
                <div class="ov-foot">
                    @include('partials.overview-change', ['change' => $outChange, 'more' => 'more', 'less' => 'less'])
                    <x-chart.spark class="out" :values="$sparks['out']" :width="72" />
                </div>
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
                <div class="ov-foot">
                    <span class="ov-note">@if ($netAverage !== null){{ count($sparks['net']) }}-month average {{ $netAverage > 0 ? '+' : '' }}{{ rand_whole($netAverage) }}@else Money in less all spending{{ $soFar }}@endif</span>
                    <x-chart.spark class="net" :values="$sparks['net']" :width="72" />
                </div>
            </section>

            <section class="ov-card c8" aria-labelledby="trend-h">
                <div class="ov-card-head">
                    <div><h2 id="trend-h">Spending and budget</h2><p class="ov-sub">Each budget month{{ count($chartMonths) > 1 ? ', the last '.count($chartMonths) : '' }}</p></div>
                    <ul class="ov-legend">
                        <li><span class="key key-bar key-out" aria-hidden="true"></span>Spent</li>
                        <li><span class="key key-line key-in" aria-hidden="true"></span>Money in</li>
                        @if ($summary->budgeted > 0)<li><span class="key key-dash" aria-hidden="true"></span>Budget</li>@endif
                    </ul>
                </div>
                <x-chart.columns :months="$chartMonths" :budget="$summary->budgeted" />
                <details class="ov-details">
                    <summary>Show as a table</summary>
                    <table class="ov-table">
                        <thead><tr><th scope="col">Month</th><th scope="col" class="num">Spent</th><th scope="col" class="num">Money in</th><th scope="col" class="num">In − spent</th></tr></thead>
                        <tbody>
                            @foreach (array_reverse($chartMonths) as $m)
                                <tr><th scope="row"><a class="ov-line" href="{{ $m['href'] }}">{{ $m['title'] }}{{ $m['running'] ? ' (so far)' : '' }}</a></th><td class="num">{{ rand_whole($m['spent']) }}</td><td class="num">{{ rand_whole($m['received']) }}</td><td class="num">{{ $m['received'] - $m['spent'] > 0 ? '+' : '' }}{{ rand_whole($m['received'] - $m['spent']) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </details>
            </section>

            <section class="ov-card c4" aria-labelledby="where-h">
                <div class="ov-card-head"><div><h2 id="where-h">Where it went</h2><p class="ov-sub">{{ $isCurrent ? 'This month so far' : $monthName }}, by group</p></div></div>
                @if ($hasGroups && $donut !== [])
                    <div class="ov-donut">
                        <x-chart.donut :segments="$donut" :total="(int) array_sum(array_column($donut, 'cents'))" :caption="$isCurrent ? 'so far' : 'spent'" />
                        <ul class="ov-donut-legend">
                            @foreach ($donut as $slice)
                                <li @class(['group-colour-'.$slice['colour'] => $slice['colour'] !== null])>
                                    <span @class(['group-dot', 'grey' => $slice['colour'] === null]) aria-hidden="true"></span>
                                    <span class="name">{{ $slice['name'] }}</span>
                                    <strong>{{ rand_whole($slice['cents']) }}</strong>
                                    <span class="pct">{{ (int) round($slice['cents'] / max(1, array_sum(array_column($donut, 'cents'))) * 100) }}%</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @elseif ($hasGroups)
                    <p class="ov-note">Nothing spent {{ $isCurrent ? 'yet this month' : 'in '.$monthName }}.</p>
                @else
                    <p class="ov-note"><a href="{{ route('budget') }}#groups-h">Make groups</a> of your budget lines, like the sections of your spreadsheet, to see where the money goes.</p>
                @endif
            </section>

            <section class="ov-card c4" aria-labelledby="pace-h">
                <div class="ov-card-head">
                    <div><h2 id="pace-h">This month's pace</h2><p class="ov-sub">Spent so far, day by day</p></div>
                    <ul class="ov-legend">
                        <li><span class="key key-line key-out" aria-hidden="true"></span>{{ $monthName }}</li>
                        @if ($pace['last'] !== [])<li><span class="key key-line key-last" aria-hidden="true"></span>{{ $lastMonthName }}</li>@endif
                        @if ($summary->budgeted > 0)<li><span class="key key-dash" aria-hidden="true"></span>Even pace</li>@endif
                    </ul>
                </div>
                <x-chart.pace :now="$pace['now']" :last="$pace['last']" :budget="$summary->budgeted" :from="$period->from" :days="$pace['days']" :now-label="$monthName" :last-label="$lastMonthName" />
            </section>

            <section class="ov-card c4" aria-labelledby="bvr-h">
                <div class="ov-card-head">
                    <div><h2 id="bvr-h">Budget vs real</h2><p class="ov-sub">{{ $hasGroups ? 'By group' : 'The biggest lines' }}</p></div>
                </div>
                <div class="ov-bars">
                    @foreach ($groupTotals as $total)
                        <div @class(['ov-bar-row', 'group-colour-'.$total['colour'] => $total['colour'] !== null])>
                            <div class="ov-bar-text">
                                @if ($total['id'] !== null)
                                    <a class="ov-bar-name" href="{{ route('transactions.index', ['group' => $total['id'], 'month' => $month]) }}">{{ $total['name'] }}</a>
                                @else
                                    <span class="ov-bar-name">{{ $total['name'] }}</span>
                                @endif
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
                @if (! $hasGroups)
                    <p class="ov-note ov-hint"><a href="{{ route('budget') }}#groups-h">Make groups</a> of your budget lines to compare them here.</p>
                @endif
            </section>

            <section class="ov-card c4" aria-labelledby="changes-h">
                <div class="ov-card-head"><div><h2 id="changes-h">Biggest changes</h2><p class="ov-sub">{{ $changesMonth !== null ? $changesMonth.' against the 6 months before' : 'Month against month' }}</p></div></div>
                @if ($changes === [])
                    <p class="ov-note">{{ $changesMonth === null ? 'There is no full month yet.' : 'Nothing changed much, or there are fewer than three months before it to compare with.' }}</p>
                @else
                    <ul class="ov-changes">
                        @foreach ($changes as $c)
                            <li>
                                <a href="{{ route('transactions.index', ['category' => $c['id'], 'month' => $changesIn]) }}">
                                    <span class="who"><span class="name">{{ $c['name'] }}</span><span class="ov-note">{{ rand_whole($c['cents']) }} against {{ rand_whole($c['average']) }} a month</span></span>
                                    <span @class(['pct', 'up' => $c['pct'] > 0, 'down' => $c['pct'] < 0])>{{ $c['pct'] > 0 ? '+' : '−' }}{{ abs($c['pct']) }}%<span class="visually-hidden"> {{ $c['pct'] > 0 ? 'more' : 'less' }}</span></span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="ov-card c12" id="lines" aria-labelledby="lines-h">
                <div class="ov-card-head">
                    <div><h2 id="lines-h">Budget lines</h2><p class="ov-sub">{{ $isCurrent ? 'This month so far' : $monthName }}, with the 6 months before</p></div>
                    @if ($hasGroups)
                        <nav class="ov-seg" aria-label="Show a group">
                            @php($ungrouped = collect($summary->spending)->contains(fn ($row) => $row['group'] === null))
                            @php($groupLinks = [['', 'All'], ...array_map(fn ($g) => [(string) $g->id, $g->name], $summary->groups), ...($ungrouped ? [['none', 'Other']] : [])])
                            @foreach ($groupLinks as [$id, $label])
                                <a href="{{ $here(['group' => $id]) }}#lines" @if ((string) $groupId === $id) aria-current="true" @endif>{{ $label }}</a>
                            @endforeach
                        </nav>
                    @endif
                </div>
                <div class="ov-table-wrap">
                    <table class="ov-table">
                        <thead>
                            <tr><th scope="col">Line</th><th scope="col" class="num">Budget</th><th scope="col" class="num">Spent</th><th scope="col" class="num">Left</th><th scope="col" class="used"><span class="visually-hidden">Share of the budget used</span></th><th scope="col" class="trend">6 months before</th><th scope="col" class="num status">Recurring</th></tr>
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
                                    <td class="trend"><x-chart.spark class="group" :values="$lineSparks[$line['id'] ?? 'out'] ?? []" :width="110" :height="24" /></td>
                                    <td class="num status"><span @class(['ov-status', $status['tone'] ?? 'muted'])>{{ $status['text'] ?? '' }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="ov-empty">Nothing here {{ $isCurrent ? 'yet this month' : 'in '.$monthName }}.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
    {{-- The hover readout; each mark says the same in its own label, so screen readers skip this. --}}
    <div class="ov-tip" aria-hidden="true" hidden></div>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/overview.js') }}" defer></script>
@endpush
