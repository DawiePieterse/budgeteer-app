@extends('layouts.app')

@section('title', 'Statements · Budgeteer')

@section('content')
    <x-back :href="route('settings')" label="More" />
    <header class="page-head"><h1>Statements</h1></header>

    @foreach ($statementsDue as $due)
        <div class="notice warn">
            <x-icon name="clock" :size="20" />
            <p><strong>{{ $due['account']->bank->label() }} {{ $due['account']->name }} ••{{ $due['account']->number_ending }}.</strong> The statement to {{ $due['expected']->format('j M') }} should be out. Upload it to fill in anything the bank emails missed.</p>
        </div>
    @endforeach

    <section class="card pad" aria-labelledby="add-h">
        <h2 id="add-h">Add a statement</h2>
        <form method="POST" action="{{ route('statements.preview') }}" id="statement-form" class="stack">
            @csrf
            <label class="dropzone">
                <span class="tile accent"><x-icon name="upload" :size="26" /></span>
                <strong>Choose a PDF statement</strong>
                <span class="hint">Standard Bank or Discovery Bank</span>
                <input type="file" id="statement-file" accept="application/pdf,.pdf" required>
            </label>
            <div class="field" id="statement-password" hidden>
                <label for="statement-password-input">This PDF has a password</label>
                <input type="password" id="statement-password-input" autocomplete="off">
            </div>
            <input type="hidden" name="text" id="statement-text">
            <p id="statement-message" class="hint" role="status"></p>
            <button type="submit" id="statement-submit" class="lg">Read statement</button>
        </form>
        <p class="notice plain small"><x-icon name="lock" :size="18" /> Read on this phone. Only the transactions are sent, never the file or its password.</p>
    </section>

    <section class="section" aria-labelledby="imported-h">
        <div class="section-head"><h2 id="imported-h">Imported</h2></div>
        <div class="card">
            @forelse ($imports as $import)
                <div class="item">
                    <span class="tile" aria-hidden="true">{{ $import->account->bank->initials() }}</span>
                    <span class="item-main">
                        <span class="item-title">{{ $import->account->name }} ••{{ $import->account->number_ending }}</span>
                        <span class="item-sub">{{ $import->period_from->format('j M Y') }} – {{ $import->period_to->format('j M Y') }} · by {{ $import->user->name }}</span>
                    </span>
                    <span class="item-end">
                        <span class="amount">{{ $import->added }} added</span>
                        @if ($import->already_there)<span class="item-sub">{{ $import->already_there }} already there</span>@endif
                    </span>
                </div>
            @empty
                <p class="empty">No statements yet.</p>
            @endforelse
        </div>
    </section>
@endsection

@push('scripts')
    <script type="module" src="{{ versioned_asset('js/statement-upload.js') }}"></script>
@endpush
