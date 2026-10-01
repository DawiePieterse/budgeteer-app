@extends('layouts.app')

@section('title', 'Phone notifications · Budgeteer')

@section('content')
    <x-back :href="route('settings')" label="More" />
    <header class="page-head">
        <h1>Phone notifications</h1>
        <p class="lead">Each person chooses for themselves.</p>
    </header>

    <section class="card" id="push" data-key="{{ config('budgeteer.push.public_key') }}" data-store="{{ route('push.store') }}" data-destroy="{{ route('push.destroy') }}" aria-label="This phone">
        <div class="item wrap">
            <span class="tile accent"><x-icon name="bell" /></span>
            <span class="item-main"><span class="item-title">This phone</span><span class="item-sub" data-push-status role="status">Checking this phone…</span></span>
            <span class="actions">
                <button type="button" class="sm" data-push-on hidden>Turn on</button>
                <button type="button" class="sm outline" data-push-off hidden>Turn off</button>
            </span>
        </div>
        <p class="empty small" data-push-ios hidden>On iPhone: open this page in Safari, tap <strong>Share</strong> then <strong>Add to Home Screen</strong>. Open Budgeteer from the new icon, come back here and turn notifications on.</p>
    </section>

    <section class="section" aria-labelledby="when-h">
        <div class="section-head"><h2 id="when-h">Tell me when</h2><span class="status ok" data-saved role="status"></span></div>
        <form method="POST" action="{{ route('push.preferences') }}" class="card" data-autosave>
            @csrf
            @foreach ([
                'notify_recurring' => 'A recurring payment is late or its amount changed',
                'notify_budget' => 'A budget line reaches '.(int) (\App\Notify\NoticeFinder::WARN_AT * 100).'% or goes over, or the whole budget goes over',
                'notify_gmail' => 'The bank emails stop coming in',
                'notify_summary' => 'A budget month ends: how it went',
                'notify_statements' => 'A new bank statement should be out and is not uploaded yet',
            ] as $field => $label)
                <label class="switch-row">
                    <span class="item-main">{{ $label }}</span>
                    <input type="checkbox" class="toggle" role="switch" name="{{ $field }}" value="1" @checked($me->{$field})>
                </label>
            @endforeach
            <div class="item" data-autosave-hide><button type="submit" class="secondary wide">Save choices</button></div>
        </form>
        <p class="section-note">Sent between 07:00 and 20:30, each warning once. Several at once come as one notification.</p>
    </section>

    @if ($devices->isNotEmpty())
        <section class="section" aria-labelledby="devices-h">
            <div class="section-head"><h2 id="devices-h">Your phones and browsers</h2></div>
            <div class="card">
                @foreach ($devices as $device)
                    <div class="item">
                        <span class="item-main"><span class="item-title">{{ $device->device ?? 'Phone' }}</span><span class="item-sub">Added {{ $device->created_at->format('j M Y') }}@if ($device->last_sent_at) · last notified {{ $device->last_sent_at->diffForHumans() }}@endif</span></span>
                        <form method="POST" action="{{ route('push.remove', $device) }}">
                            @csrf
                            <button type="submit" class="link danger">Remove</button>
                        </form>
                    </div>
                @endforeach
            </div>
            <form method="POST" action="{{ route('push.test') }}">
                @csrf
                <button type="submit" class="secondary wide">Send a test notification</button>
            </form>
        </section>
    @endif
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/push.js') }}" defer></script>
@endpush
