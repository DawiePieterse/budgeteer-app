@extends('layouts.app')

@section('title', 'Review · Budgeteer')

@section('content')
    <header class="page-head">
        <h1>Review</h1>
        @if ($remaining > 0)
            <p class="lead"><strong>{{ $remaining }} left</strong>, grouped by shop, biggest first. Each choice is remembered for the next statement.</p>
        @endif
    </header>

    @php($byId = $categories->flatten()->keyBy('id'))
    @forelse ($groups as $group)
        @php($suggested = $group->suggested ? $byId->get($group->suggested) : null)
        @php($first = \Carbon\Carbon::parse($group->first_on))
        @php($last = \Carbon\Carbon::parse($group->last_on))
        <article class="card pad" aria-labelledby="group-{{ $loop->index }}">
            <div class="group-head">
                <span @class(['tile', 'ok' => $group->money_in])>
                    @if ($suggested)
                        <x-category-icon :icon="$suggested->iconName()" :size="22" />
                    @else
                        <x-icon :name="$group->money_in ? 'money' : 'question'" />
                    @endif
                </span>
                <span class="item-main">
                    <span class="item-title" id="group-{{ $loop->index }}">{{ readable($group->merchant_key) }}</span>
                    <span class="item-sub">{{ $group->n }} {{ $group->money_in ? ($group->n == 1 ? 'payment in' : 'payments in') : ($group->n == 1 ? 'payment' : 'payments') }} · {{ $first->isSameDay($last) ? $last->format('j M Y') : $first->format('j M').' – '.$last->format('j M Y') }}</span>
                    <span class="group-example">e.g. {{ $group->example }}</span>
                </span>
                <span @class(['group-total', 'in' => $group->money_in])>{{ $group->money_in ? '+' : '' }}{{ money((int) $group->total) }}</span>
            </div>

            @if ($suggested)
                <form method="POST" action="{{ route('categorise.store') }}">
                    @csrf
                    <input type="hidden" name="merchant_key" value="{{ $group->merchant_key }}">
                    <input type="hidden" name="money_in" value="{{ $group->money_in ? 1 : 0 }}">
                    <input type="hidden" name="category" value="{{ $suggested->id }}">
                    <button type="submit" class="wide"><x-icon name="check" :size="20" /><span class="visually-hidden">Put in</span> {{ $suggested->name }}</button>
                </form>
            @endif

            <form method="POST" action="{{ route('categorise.store') }}" class="stack">
                @csrf
                <input type="hidden" name="merchant_key" value="{{ $group->merchant_key }}">
                <input type="hidden" name="money_in" value="{{ $group->money_in ? 1 : 0 }}">
                <div class="choose-row">
                    <label class="visually-hidden" for="category-{{ $loop->index }}">Category for {{ readable($group->merchant_key) }}</label>
                    <select name="category" id="category-{{ $loop->index }}" required @if ($group->n == 1 && ! $group->money_in) data-reveal="{{ \App\Http\Controllers\CategoriseController::NEW_PERSON }}" data-reveal-target="new-person-{{ $loop->index }}" @endif>
                        <option value="">{{ $suggested ? 'Something else…' : 'Choose…' }}</option>
                        @foreach (['expense' => 'Spending', 'income' => 'Money in'] as $kind => $label)
                            <optgroup label="{{ $label }}">
                                @foreach ($categories[$kind] ?? [] as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                        @if ($projects->isNotEmpty())
                            <optgroup label="Special projects (outside the budget)">
                                @foreach ($projects as $project)
                                    <option value="{{ \App\Http\Controllers\CategoriseController::PROJECT }}{{ $project->id }}">{{ $project->name }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                        @if ($group->n == 1 && ! $group->money_in)
                            <optgroup label="Bought for someone (they pay back)">
                                @foreach ($people as $person)
                                    <option value="{{ \App\Http\Controllers\CategoriseController::PERSON }}{{ $person->id }}">For {{ $person->name }}</option>
                                @endforeach
                                <option value="{{ \App\Http\Controllers\CategoriseController::NEW_PERSON }}">For someone new…</option>
                            </optgroup>
                        @endif
                        <optgroup label="Not spending">
                            <option value="{{ \App\Http\Controllers\CategoriseController::TRANSFER }}">Between our own accounts</option>
                        </optgroup>
                    </select>
                    <button type="submit" @class(['secondary' => $suggested])>Save</button>
                </div>
                @if ($group->n == 1 && ! $group->money_in)
                    <div class="field" id="new-person-{{ $loop->index }}">
                        <label for="new-person-name-{{ $loop->index }}">Their name</label>
                        <input type="text" name="new_person" id="new-person-name-{{ $loop->index }}" maxlength="100" autocomplete="off">
                    </div>
                @endif
            </form>
            @if ($group->n > 1 && ! $group->money_in)
                <a class="small" href="{{ route('transactions.index', ['category' => 'none', 'merchant' => $group->merchant_key]) }}">One of these bought for someone else? Open them one by one</a>
            @endif
        </article>
    @empty
        <div class="card empty-state">
            <span class="tile lg ok"><x-icon name="check" :size="28" /></span>
            <strong>All caught up</strong>
            <span class="muted">Everything is categorised.</span>
            <a href="{{ route('home') }}">Back to Home</a>
        </div>
    @endforelse
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/reveal.js') }}" defer></script>
@endpush
