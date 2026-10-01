@extends('layouts.app')

@section('title', 'Sign in · Budgeteer')

@section('content')
    <div class="signin">
        <div class="signin-main">
            <div class="signin-brand">
                <x-app-mark />
                <div>
                    <h1>Budgeteer</h1>
                    <p class="tagline">The household budget, filled in from the bank.</p>
                </div>
            </div>
            <div class="stack">
                <a class="button google-button" href="{{ route('google.redirect') }}">Sign in with Google</a>
                <p class="hint signin-foot">Only people on the household's list can sign in.</p>
            </div>

            @if ($devUsers->isNotEmpty())
                <div class="dev">
                    <p class="hint">Development: sign in without Google</p>
                    @foreach ($devUsers as $user)
                        <form method="POST" action="{{ route('dev-login', $user) }}">
                            @csrf
                            <button type="submit" class="outline wide">Sign in as {{ $user->name }}</button>
                        </form>
                    @endforeach
                </div>
            @endif
        </div>
        <p class="signin-foot"><a href="{{ route('privacy') }}">Privacy</a></p>
    </div>
@endsection
