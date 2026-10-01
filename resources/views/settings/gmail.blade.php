@extends('layouts.app')

@section('title', 'Bank emails · Budgeteer')

@section('content')
    <x-back :href="route('settings')" label="More" />
    <header class="page-head">
        <h1>Bank emails</h1>
        <p class="lead">Budgeteer reads the bank's notification emails in Gmail every five minutes, so card payments show up before the statement. It can only read, never send or delete, and only emails with the <strong>{{ \App\Models\GmailConnection::LABEL }}</strong> label.</p>
    </header>

    <section class="card" aria-label="Linked Gmail">
        @foreach ($connections as $connection)
            @php($ok = $connection->status === \App\Models\GmailConnection::ACTIVE)
            <div class="item wrap">
                <span @class(['tile', 'ok' => $ok, 'warn' => ! $ok])><x-icon :name="$ok ? 'mail' : 'alert'" /></span>
                <span class="item-main">
                    <span class="item-title">{{ $connection->email }}</span>
                    <span class="item-sub">
                        @switch($connection->status)
                            @case(\App\Models\GmailConnection::ACTIVE)
                                Reading emails labelled <strong>{{ \App\Models\GmailConnection::LABEL }}</strong>@if ($connection->last_synced_at) · checked {{ $connection->last_synced_at->diffForHumans() }}@endif
                                @break
                            @case(\App\Models\GmailConnection::LABEL_MISSING)
                                There is no Gmail label called <strong>{{ \App\Models\GmailConnection::LABEL }}</strong> yet. Create the filter below.
                                @break
                            @case(\App\Models\GmailConnection::NEEDS_RELINK)
                                Google no longer allows reading this Gmail. Link it again.
                                @break
                            @default
                                Last check failed: {{ $connection->last_error }}
                        @endswitch
                    </span>
                </span>
                <span class="actions">
                    <form method="POST" action="{{ route('gmail.sync', $connection) }}">
                        @csrf
                        <button type="submit" class="secondary sm">Check now</button>
                    </form>
                    <form method="POST" action="{{ route('gmail.destroy', $connection) }}">
                        @csrf
                        <button type="submit" class="link danger">Unlink</button>
                    </form>
                </span>
            </div>
        @endforeach
        <div class="item">
            <a @class(['button', 'wide', 'secondary' => $connections->isNotEmpty()]) href="{{ route('gmail.link') }}">{{ $connections->isEmpty() ? 'Link Gmail' : 'Link again or add another' }}</a>
        </div>
    </section>

    <details class="card disclosure">
        <summary class="item">
            <span class="tile"><x-icon name="info" /></span>
            <span class="item-main"><span class="item-title">How to label the bank emails</span><span class="item-sub">A Gmail filter, made once on a computer</span></span>
            <x-icon name="forward" class="chev" :size="20" />
        </summary>
        <div class="disclosure-body">
            <ol>
                <li>In Gmail on a computer, search for <code>from:(discovery OR standardbank) subject:("Transaction update" OR MyUpdates)</code>.</li>
                <li>Click the filter icon in the search box, then <strong>Create filter</strong>.</li>
                <li>Tick <strong>Apply the label</strong>, choose <strong>New label…</strong>, name it <code>{{ \App\Models\GmailConnection::LABEL }}</code>.</li>
                <li>Tick <strong>Also apply filter to matching conversations</strong> and click <strong>Create filter</strong>.</li>
            </ol>
            <p>For the items in Takealot and Amazon.co.za orders, make a second filter the same way with the search <code>from:(info@takealot.com OR auto-confirm@amazon.co.za)</code> and the same label.</p>
        </div>
    </details>

    @if ($emails->isNotEmpty())
        <section class="section" aria-labelledby="emails-h">
            <div class="section-head"><h2 id="emails-h">Latest bank emails</h2></div>
            <div class="card">
                @foreach ($emails as $email)
                    <div class="item">
                        <span class="item-main">
                            <span class="item-title clip">{{ $email->transaction?->description ?? $email->subject }}</span>
                            <span class="item-sub">{{ $email->received_at?->format('j M H:i') }} · {{ $email->sender }}@if ($email->note) · {{ $email->note }}@endif</span>
                        </span>
                        <span class="item-end">
                            @if ($email->transaction)<span class="amount">{{ money($email->transaction->amount_cents) }}</span>@endif
                            <span @class(['item-sub', 'status ok' => in_array($email->status, ['added', 'matched', 'order'], true), 'status warn' => in_array($email->status, ['unrecognised', 'failed'], true)])>
                                @switch($email->status)
                                    @case('added') Added @break
                                    @case('matched') On a statement @break
                                    @case('ignored') Skipped @break
                                    @case('order') Order @break
                                    @case('unrecognised') Not read @break
                                    @default Failed
                                @endswitch
                            </span>
                        </span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
@endsection
