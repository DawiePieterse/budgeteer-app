@extends('layouts.app')

@section('title', 'Sign in · Budgeteer')

@section('content')
    <section class="card center">
        <h1>Budgeteer</h1>
        <p class="muted">The household budget, filled in from the bank.</p>
        <a class="button" href="{{ route('google.redirect') }}">Sign in with Google</a>
        <p class="small"><a href="{{ route('privacy') }}">Privacy</a></p>

        @if ($devUsers->isNotEmpty())
            <div class="dev">
                <p class="muted small">Development: sign in without Google</p>
                @foreach ($devUsers as $user)
                    <form method="POST" action="{{ route('dev-login', $user) }}">
                        @csrf
                        <button type="submit" class="secondary">Sign in as {{ $user->name }}</button>
                    </form>
                @endforeach
            </div>
        @endif
    </section>
@endsection
