@extends('layouts.app')

@section('title', 'Transaction · Budgeteer')

@section('content')
    <h1>{{ $transaction->description }}</h1>

    <section class="card">
        <div class="row"><span>Amount</span><span @class(['amount', 'in' => $transaction->amount_cents > 0])>{{ money($transaction->amount_cents) }}</span></div>
        <div class="row"><span>Date</span><span>{{ $transaction->posted_on->format('j F Y') }}</span></div>
        <div class="row"><span>Account</span><span>{{ $transaction->account->name }} ••{{ $transaction->account->number_ending }}</span></div>
        <div class="row"><span>Kind</span><span>{{ $transaction->kind->label() }}@if ($transaction->bank_type) <span class="muted small block">{{ $transaction->bank_type }}</span>@endif</span></div>
    </section>

    @if ($order = $transaction->order)
        <section class="card">
            <h2>{{ $order->shopName() }} order {{ $order->order_number }}</h2>
            @foreach ($order->items as $item)
                <div class="row">
                    <span>{{ $item->name }}@if ($item->quantity > 1) <span class="muted small block">× {{ $item->quantity }}</span>@endif</span>
                    @if ($item->price_cents !== null)<span class="amount">{{ money($item->price_cents) }}</span>@endif
                </div>
            @endforeach
            <div class="row"><strong>Order total</strong><strong class="amount">{{ money($order->total_cents) }}</strong></div>
            <p class="muted small">
                Ordered {{ $order->ordered_at->format('j M Y H:i') }}@if ($order->deliver_to) · delivered to {{ $order->deliver_to }}@endif
                · <a href="{{ $order->url() }}" rel="noopener" target="_blank">Open on {{ $order->shopName() }}</a>
            </p>
            @if ($deliveredToSomeone)
                <p class="notice small">This went to {{ $deliveredToSomeone->name }}. If it was bought for them, choose <strong>Bought for {{ $deliveredToSomeone->name }}</strong> below.</p>
            @endif
        </section>
    @endif

    <form method="POST" action="{{ route('transactions.update', $transaction) }}" class="card">
        @csrf
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
        <label for="person_id">Whose spending</label>
        <select name="person_id" id="person_id" data-reveal="new" data-reveal-target="new-person-field">
            <option value="">Ours (in the budget)</option>
            @foreach ($people as $person)
                <option value="{{ $person->id }}" @selected((string) old('person_id', $transaction->person_id) === (string) $person->id)>Bought for {{ $person->name }} (they pay back)</option>
            @endforeach
            <option value="{{ \App\Http\Controllers\TransactionController::NEW_PERSON }}" @selected(old('person_id') === \App\Http\Controllers\TransactionController::NEW_PERSON)>Bought for someone new…</option>
        </select>
        <div id="new-person-field">
            <label for="new_person">Their name</label>
            <input type="text" name="new_person" id="new_person" value="{{ old('new_person') }}" maxlength="100" autocomplete="off">
        </div>
        <p class="muted small">Something bought for someone else stays out of the budget and is added to what they owe you.</p>
        @if ($projects->isNotEmpty())
            <label for="project_id">Special project</label>
            <select name="project_id" id="project_id">
                <option value="">None (monthly budget)</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" @selected($transaction->project_id === $project->id)>{{ $project->name }}</option>
                @endforeach
            </select>
        @endif
        <label class="check">
            <input type="hidden" name="is_transfer" value="0">
            <input type="checkbox" name="is_transfer" value="1" @checked($transaction->is_transfer)>
            Between our own accounts (not spending or income)
        </label>
        <button type="submit">Save</button>
    </form>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/reveal.js') }}" defer></script>
@endpush
