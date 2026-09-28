<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

function googleReturns(string $email, string $id = 'g-1'): void
{
    $google = (new GoogleUser)->map(['id' => $id, 'name' => 'Jane Smith', 'email' => $email, 'avatar' => null]);
    Socialite::shouldReceive('driver->user')->andReturn($google);
}

it('sends guests to the sign-in page', function () {
    $this->get('/')->assertRedirect('/login');
    $this->get('/login')->assertOk()->assertSee('Sign in with Google');
});

it('signs in someone on the allowlist', function () {
    $user = member();
    googleReturns(strtoupper($user->email));

    $this->get('/auth/google/callback')->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->google_id)->toBe('g-1');
});

it('refuses a Google account that is not on the allowlist', function () {
    member();
    googleReturns('stranger@example.com');

    $this->get('/auth/google/callback')->assertRedirect('/login');

    $this->assertGuest();
});

it('refuses a different Google account using a known address', function () {
    $user = member();
    $user->update(['google_id' => 'g-original']);
    googleReturns($user->email, 'g-other');

    $this->get('/auth/google/callback')->assertRedirect('/login');

    $this->assertGuest();
});

it('offers no development sign-in outside development', function () {
    $user = member();

    $this->post("/dev-login/{$user->id}")->assertNotFound();
    $this->get('/login')->assertDontSee('Sign in as');
});

it('signs out', function () {
    $user = member();

    $this->actingAs($user)->post('/logout')->assertRedirect('/login');

    $this->assertGuest();
});

it('sends security headers', function () {
    $this->get('/login')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Content-Security-Policy');
});

it('creates the household and allowlist from the setup command', function () {
    $this->artisan('budgeteer:setup', ['--name' => 'Smith', '--emails' => 'A@example.com, b@example.com'])->assertSuccessful();

    expect(User::pluck('email')->all())->toBe(['a@example.com', 'b@example.com'])
        ->and(User::first()->household->name)->toBe('Smith');
});

it('shows the privacy page to anyone', function () {
    $this->get('/privacy')->assertOk()->assertSee('Google API Services User Data Policy');
    $this->actingAs(member())->get('/privacy')->assertOk();
});
