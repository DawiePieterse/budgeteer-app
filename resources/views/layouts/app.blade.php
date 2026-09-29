<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f766e">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Budgeteer')</title>
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Budgeteer">
    <link rel="stylesheet" href="{{ versioned_asset('css/app.css') }}">
</head>
<body>
    <header class="top">
        <a href="{{ route('home') }}" class="brand">Budgeteer</a>
        @auth
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="link">Sign out</button>
            </form>
        @endauth
    </header>

    <main>
        @if (session('status'))
            <p class="notice ok" role="status">{{ session('status') }}</p>
        @endif
        @if (session('error'))
            <p class="notice error" role="alert">{{ session('error') }}</p>
        @endif
        @if ($errors->any())
            <div class="notice error" role="alert">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        @yield('content')
    </main>

    @auth
        <nav class="tabs" aria-label="Main">
            <a href="{{ route('home') }}" @class(['on' => request()->routeIs('home')])>Home</a>
            <a href="{{ route('categorise') }}" @class(['on' => request()->routeIs('categorise')])>Categorise</a>
            <a href="{{ route('transactions.index') }}" @class(['on' => request()->routeIs('transactions.*')])>Transactions</a>
            <a href="{{ route('statements.index') }}" @class(['on' => request()->routeIs('statements.*')])>Statements</a>
            <a href="{{ route('settings') }}" @class(['on' => request()->routeIs('settings')])>Settings</a>
        </nav>
    @endauth

    @stack('scripts')
</body>
</html>
