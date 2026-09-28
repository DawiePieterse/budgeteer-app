@extends('layouts.app')

@section('title', 'Statements · Budgeteer')

@section('content')
    <h1>Statements</h1>

    <section class="card">
        <h2>Add a statement</h2>
        <p class="muted small">A PDF statement from Standard Bank or Discovery Bank. It is read on this phone; only the transactions are sent, never the file.</p>

        <form method="POST" action="{{ route('statements.preview') }}" id="statement-form">
            @csrf
            <input type="file" id="statement-file" accept="application/pdf,.pdf" required>
            <div id="statement-password" hidden>
                <label for="statement-password-input">This PDF has a password</label>
                <input type="password" id="statement-password-input" autocomplete="off">
            </div>
            <input type="hidden" name="text" id="statement-text">
            <p id="statement-message" class="muted small" role="status"></p>
            <button type="submit" id="statement-submit">Read statement</button>
        </form>
    </section>

    <section class="card">
        <h2>Imported</h2>
        @forelse ($imports as $import)
            <div class="row">
                <span>
                    {{ $import->account->name }} <span class="muted small">••{{ $import->account->number_ending }}</span>
                    <span class="muted small block">{{ $import->period_from->format('j M Y') }} – {{ $import->period_to->format('j M Y') }} · by {{ $import->user->name }}</span>
                </span>
                <span class="amount small">{{ $import->added }} added @if ($import->already_there) <span class="muted block">{{ $import->already_there }} already there</span> @endif</span>
            </div>
        @empty
            <p class="muted">No statements yet.</p>
        @endforelse
    </section>
@endsection

@push('scripts')
    <script type="module" src="{{ asset('js/statement-upload.js') }}?v=1"></script>
@endpush
