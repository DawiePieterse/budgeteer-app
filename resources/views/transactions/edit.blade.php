@extends('layouts.app')

@section('title', 'Transaction · Budgeteer')

@section('content')
    @php
        $toReview = ! $transaction->is_transfer && $transaction->category_id === null && $transaction->person_id === null && $transaction->project_id === null;
        $colour = $toReview ? null : $transaction->ownerColour();
        $whose = match (true) {
            $transaction->is_transfer => 'Between our own accounts',
            $transaction->project !== null => $transaction->project->name.', a special project',
            $transaction->person !== null => $transaction->person->name.' pays it back',
            $toReview => 'Not categorised yet',
            default => 'Ours, in the budget',
        };
        $chosenPerson = (string) old('person_id', $transaction->person_id);
    @endphp

    <x-back :href="route('transactions.index')" label="Transactions" />

    <header class="tx-head {{ $colour ? 'owner-'.$colour : 'owner-none' }}">
        <span @class(['tile', 'lg', 'owned' => $colour !== null, 'todo' => $toReview])>
            @if ($transaction->is_transfer)
                <x-icon name="transfer" :size="28" />
            @elseif ($transaction->category)
                <x-category-icon :icon="$transaction->category->iconName()" :size="28" />
            @else
                <x-icon name="question" :size="28" />
            @endif
        </span>
        <h1>{{ $transaction->displayName() }}</h1>
        @if ($transaction->displayName() !== $transaction->description)
            <p class="tx-raw">{{ $transaction->description }}</p>
        @endif
        <p @class(['tx-amount', 'in' => $transaction->amount_cents > 0])>{{ $transaction->amount_cents > 0 ? '+' : '' }}{{ money($transaction->amount_cents) }}</p>
        <p class="tx-date">{{ $transaction->posted_on->format('D j F Y') }}</p>
        <p @class(['pill', 'owned' => $colour !== null, 'warn' => $toReview])>{{ $whose }}</p>
    </header>

    <dl class="card">
        <div class="kv"><dt>Account</dt><dd>{{ $transaction->account->name }} ••{{ $transaction->account->number_ending }}</dd></div>
        @if ($transaction->card)
            <div class="kv"><dt>Card</dt><dd>••{{ $transaction->card->number_ending }} · {{ $transaction->card->holder_name ?? 'main cardholder' }}</dd></div>
        @endif
        <div class="kv"><dt>Kind</dt><dd>{{ $transaction->kind->label() }}@if ($transaction->bank_type) <span class="muted small block">{{ $transaction->bank_type }}</span>@endif</dd></div>
        <div class="kv"><dt>Read from</dt><dd>
            @switch($transaction->source)
                @case(\App\Enums\TransactionSource::Statement) Bank statement @break
                @case(\App\Enums\TransactionSource::Email) {{ $transaction->statement_import_id ? 'Bank email and statement' : 'Bank email' }} @break
                @default Typed in
            @endswitch
        </dd></div>
    </dl>

    @if ($order = $transaction->order)
        <section class="card" aria-labelledby="order-h">
            <div class="item tight">
                <h2 class="item-main" id="order-h">{{ $order->shopName() }} order {{ $order->order_number }}</h2>
                <a class="button ghost sm" href="{{ $order->url() }}" rel="noopener" target="_blank">Open <x-icon name="external" :size="16" /></a>
            </div>
            @foreach ($order->items as $item)
                <div class="kv">
                    <span>{{ $item->name }}@if ($item->quantity > 1) <span class="muted">× {{ $item->quantity }}</span>@endif</span>
                    @if ($item->price_cents !== null)<span class="amount">{{ money($item->price_cents) }}</span>@endif
                </div>
            @endforeach
            <div class="kv"><strong>Order total</strong><strong class="amount">{{ money($order->total_cents) }}</strong></div>
            <p class="empty small">Ordered {{ $order->ordered_at->format('j M Y, H:i') }}@if ($order->deliver_to) · delivered to {{ $order->deliver_to }}@endif</p>
            @if ($deliveredToSomeone)
                <form method="POST" action="{{ route('transactions.update', $transaction) }}" class="notice info">
                    @csrf
                    <input type="hidden" name="person_id" value="{{ $deliveredToSomeone->id }}">
                    <input type="hidden" name="category_id" value="{{ $transaction->category_id }}">
                    <input type="hidden" name="is_transfer" value="0">
                    <div class="notice-body">
                        <p>This went to {{ $deliveredToSomeone->name }}. Was it bought for them?</p>
                        <button type="submit" class="sm">Bought for {{ $deliveredToSomeone->name }}</button>
                    </div>
                </form>
            @endif
        </section>
    @endif

    <form method="POST" action="{{ route('transactions.update', $transaction) }}" class="card pad">
        @csrf
        <h2>Where it goes</h2>
        <div class="field">
            <label for="category_id">Category</label>
            <select name="category_id" id="category_id">
                <option value="">Not categorised</option>
                @foreach (['expense' => 'Spending', 'income' => 'Money in'] as $kind => $label)
                    <optgroup label="{{ $label }}">
                        @foreach ($categories[$kind] ?? [] as $category)
                            <option value="{{ $category->id }}" @selected($transaction->category_id === $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </div>

        <fieldset class="choices" data-reveal="{{ \App\Http\Controllers\TransactionController::NEW_PERSON }}" data-reveal-target="new-person-field">
            <legend>Whose spending</legend>
            <label class="choice">
                <input type="radio" name="person_id" value="" @checked($chosenPerson === '')>
                <span class="choice-text"><strong>Ours</strong><span>In the monthly budget</span></span>
            </label>
            @foreach ($people as $person)
                <label class="choice">
                    <input type="radio" name="person_id" value="{{ $person->id }}" @checked($chosenPerson === (string) $person->id)>
                    <span class="choice-text"><strong>Bought for {{ $person->name }}</strong><span>Out of the budget, added to what {{ $person->name }} owes</span></span>
                </label>
            @endforeach
            <label class="choice">
                <input type="radio" name="person_id" value="{{ \App\Http\Controllers\TransactionController::NEW_PERSON }}" @checked($chosenPerson === \App\Http\Controllers\TransactionController::NEW_PERSON)>
                <span class="choice-text"><strong>Bought for someone new…</strong><span>Out of the budget; type their name</span></span>
            </label>
            <div class="field" id="new-person-field">
                <label for="new_person">Their name</label>
                <input type="text" name="new_person" id="new_person" value="{{ old('new_person') }}" maxlength="100" autocomplete="off">
            </div>
        </fieldset>

        @if ($projects->isNotEmpty())
            <div class="field">
                <label for="project_id">Special project</label>
                <select name="project_id" id="project_id">
                    <option value="">None (monthly budget)</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}" @selected($transaction->project_id === $project->id)>{{ $project->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <label class="switch-row bare">
            <span class="item-main"><span class="item-title">Between our own accounts</span><span class="item-sub">Not spending or income</span></span>
            <input type="hidden" name="is_transfer" value="0">
            <input type="checkbox" class="toggle" role="switch" name="is_transfer" value="1" @checked($transaction->is_transfer)>
        </label>

        <button type="submit" class="lg">Save</button>
    </form>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/reveal.js') }}" defer></script>
@endpush
