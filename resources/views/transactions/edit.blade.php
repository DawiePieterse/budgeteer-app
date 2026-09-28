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
        <label class="check">
            <input type="hidden" name="is_transfer" value="0">
            <input type="checkbox" name="is_transfer" value="1" @checked($transaction->is_transfer)>
            Between our own accounts (not spending or income)
        </label>
        <button type="submit">Save</button>
    </form>
@endsection
