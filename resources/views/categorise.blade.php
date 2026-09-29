@extends('layouts.app')

@section('title', 'Categorise · Budgeteer')

@section('content')
    <h1>Categorise</h1>
    <p class="muted small">
        @if ($remaining > 0)
            {{ $remaining }} transactions left, grouped by merchant, biggest first. Each choice is remembered for next time.
        @else
            Everything is categorised.
        @endif
    </p>

    @foreach ($groups as $group)
        <form method="POST" action="{{ route('categorise.store') }}" class="card group">
            @csrf
            <input type="hidden" name="merchant_key" value="{{ $group->merchant_key }}">
            <input type="hidden" name="money_in" value="{{ $group->money_in ? 1 : 0 }}">
            <div class="row">
                <span>
                    <strong>{{ $group->merchant_key }}</strong>
                    <span class="muted small block">{{ $group->n }} × · {{ \Carbon\Carbon::parse($group->first_on)->format('j M') }} – {{ \Carbon\Carbon::parse($group->last_on)->format('j M Y') }}</span>
                    <span class="muted small block">e.g. {{ $group->example }}</span>
                </span>
                <span @class(['amount', 'in' => $group->money_in])>{{ money((int) $group->total) }}</span>
            </div>
            <div class="inline">
                <label class="visually-hidden" for="category-{{ $loop->index }}">Category</label>
                <select name="category" id="category-{{ $loop->index }}" required @class(['suggested' => $group->suggested ?? null]) @if ($group->n == 1 && ! $group->money_in) data-reveal="{{ \App\Http\Controllers\CategoriseController::NEW_PERSON }}" data-reveal-target="new-person-{{ $loop->index }}" @endif>
                    <option value="">Choose…</option>
                    @foreach (['expense' => 'Spending', 'income' => 'Money in'] as $kind => $label)
                        <optgroup label="{{ $label }}">
                            @foreach ($categories[$kind] ?? [] as $category)
                                <option value="{{ $category->id }}" @selected(($group->suggested ?? null) === $category->id)>{{ $category->name }}</option>
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
                <button type="submit">Save</button>
            </div>
            @if ($group->n == 1 && ! $group->money_in)
                <div id="new-person-{{ $loop->index }}">
                    <label for="new-person-name-{{ $loop->index }}">Their name</label>
                    <input type="text" name="new_person" id="new-person-name-{{ $loop->index }}" maxlength="100" autocomplete="off">
                </div>
            @elseif (! $group->money_in)
                <p class="small"><a href="{{ route('transactions.index', ['category' => 'none', 'merchant' => $group->merchant_key]) }}">One of these bought for someone else? Open them one by one ›</a></p>
            @endif
        </form>
    @endforeach
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/reveal.js') }}" defer></script>
@endpush
