<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#f3f5f4" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0e1413" media="(prefers-color-scheme: dark)">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Budgeteer')</title>
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Budgeteer">
    <link rel="preload" href="{{ asset('fonts/Geist-Variable.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ versioned_asset('css/app.css') }}">
</head>
<body @auth class="has-nav" @endauth>
    @auth
        @php
            // Phones have five tabs; a wide screen lists more in a sidebar. Recurring, for example, is part of
            // the Budget tab on a phone and its own item in the sidebar.
            $is = fn (string ...$names) => request()->routeIs(...$names);
            $items = [
                ['home', 'Home', null, 'home', $is('home', 'people.show'), $is('home'), true],
                ['transactions.index', 'Transactions', null, 'list', $is('transactions.*'), $is('transactions.*'), true],
                ['categorise', 'Review', null, 'inbox', $is('categorise'), $is('categorise'), true],
                ['budget', 'Budget', null, 'pie', $is('budget', 'recurring.*', 'projects.*'), $is('budget', 'projects.*'), true],
                ['recurring.index', 'Recurring', null, 'repeat', false, $is('recurring.*'), false],
                ['statements.index', 'Statements', null, 'upload', false, $is('statements.*'), false],
                ['people.index', 'People', null, 'people', false, $is('people.*'), false],
                ['settings', 'More', 'Settings', 'menu', $is('settings*', 'statements.*', 'people.index', 'privacy'), $is('settings*', 'privacy'), true],
            ];
        @endphp
        <nav class="nav" aria-label="Main">
            <a class="nav-brand" href="{{ route('home') }}"><x-app-mark small /> Budgeteer</a>
            <div class="nav-items">
                @foreach ($items as [$route, $label, $wideLabel, $icon, $onPhone, $onWide, $onPhoneTabs])
                    <a href="{{ route($route) }}"
                        @class(['nav-item', 'wide-only' => ! $onPhoneTabs, 'on-phone' => $onPhone, 'on-wide' => $onWide])
                        @if ($onWide) aria-current="page" @elseif ($onPhone) aria-current="true" @endif>
                        <span class="nav-icon"><x-icon :name="$icon" :size="24" />@if ($route === 'categorise' && $reviewCount > 0)<span class="badge" aria-hidden="true">{{ $reviewCount > 99 ? '99+' : $reviewCount }}</span>@endif</span>
                        @if ($wideLabel)
                            <span class="nav-label-phone">{{ $label }}</span><span class="nav-label-wide">{{ $wideLabel }}</span>
                        @else
                            <span class="nav-label">{{ $label }}</span>
                        @endif
                        @if ($route === 'categorise' && $reviewCount > 0)
                            <span class="badge nav-wide-badge" aria-hidden="true">{{ $reviewCount }}</span>
                            <span class="visually-hidden">, {{ $reviewCount }} to review</span>
                        @endif
                    </a>
                @endforeach
            </div>
            <div class="nav-user">
                <span class="avatar" aria-hidden="true">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                <span class="item-main"><span class="item-title">{{ auth()->user()->name }}</span><span class="item-sub clip">{{ auth()->user()->household->name }}</span></span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="icon-button" aria-label="Sign out"><x-icon name="signout" :size="20" /></button>
                </form>
            </div>
        </nav>
    @endauth

    <main @class(['page', 'guest' => ! auth()->check()])>
        @if (session('status'))
            <div class="notice ok" role="status"><x-icon name="check" :size="20" /><p>{{ session('status') }}</p></div>
        @endif
        @if (session('error'))
            <div class="notice error" role="alert"><x-icon name="alert" :size="20" /><p>{{ session('error') }}</p></div>
        @endif
        @if ($errors->any())
            <div class="notice error" role="alert">
                <x-icon name="alert" :size="20" />
                <div class="notice-body">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
