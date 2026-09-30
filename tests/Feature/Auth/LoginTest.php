<?php

use App\Livewire\Auth\Login;
use App\Models\LoginActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

it('renders the Arabic right-to-left login page for guests', function () {
    $response = $this->get(route('login'));

    $response
        ->assertOk()
        ->assertSee('lang="ar"', escape: false)
        ->assertSee('dir="rtl"', escape: false)
        ->assertSee('أهلًا بعودتك')
        ->assertSee('data-login-password-control', escape: false)
        ->assertSee('min-w-0 flex-1', escape: false)
        ->assertSee('w-11 shrink-0', escape: false);
});

it('renders the responsive Arabic application shell for an authenticated user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertSee('lang="ar"', escape: false)
        ->assertSee('dir="rtl"', escape: false)
        ->assertSee('مساحة العمل الآمنة')
        ->assertSee($user->name);
});

it('authenticates an active user with a normalized username and records the login', function () {
    $user = User::factory()->create([
        'username' => 'clinic.admin',
        'password' => 'password',
        'is_active' => true,
        'last_login_at' => null,
        'remember_token' => null,
    ]);

    Livewire::test(Login::class)
        ->set('username', '  CLINIC.ADMIN  ')
        ->set('password', 'password')
        ->set('remember', true)
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertSet('username', 'clinic.admin')
        ->assertRedirect();

    $this->assertAuthenticatedAs($user);
    $user->refresh();

    expect($user->last_login_at)->not->toBeNull()
        ->and($user->remember_token)->not->toBeNull();

    $activity = LoginActivity::query()->sole();

    expect($activity->user_id)->toBe($user->id)
        ->and($activity->login_at)->not->toBeNull()
        ->and($activity->logout_at)->toBeNull()
        ->and($activity->session_identifier)->not->toBeEmpty();
});

it('rejects invalid credentials without creating login activity', function () {
    $user = User::factory()->create([
        'username' => 'clinic.user',
        'password' => 'correct-password',
    ]);

    Livewire::test(Login::class)
        ->set('username', 'clinic.user')
        ->set('password', 'wrong-password')
        ->call('authenticate')
        ->assertHasErrors(['username'])
        ->assertSet('password', '')
        ->assertSee('اسم المستخدم أو كلمة المرور غير صحيحة.');

    $this->assertGuest();
    $this->assertDatabaseCount('login_activities', 0);
    expect($user->refresh()->last_login_at)->toBeNull();
});

it('rate limits repeated attempts using normalized username and IP', function () {
    config()->set('clinic.auth.login.max_attempts', 2);
    config()->set('clinic.auth.login.decay_seconds', 60);

    Livewire::test(Login::class)
        ->set('username', '  RATE.LIMITED  ')
        ->set('password', 'wrong-password')
        ->call('authenticate')
        ->assertHasErrors(['username']);

    Livewire::test(Login::class)
        ->set('username', 'rate.limited')
        ->set('password', 'wrong-password')
        ->call('authenticate')
        ->assertHasErrors(['username']);

    Livewire::test(Login::class)
        ->set('username', 'RATE.LIMITED')
        ->set('password', 'wrong-password')
        ->call('authenticate')
        ->assertHasErrors(['username'])
        ->assertSet('password', '')
        ->assertSee('محاولات كثيرة. يرجى المحاولة مجددًا بعد');

    $key = hash('sha256', 'rate.limited|127.0.0.1');
    expect(RateLimiter::attempts($key))->toBe(2);

    RateLimiter::clear($key);
});

it('does not authenticate an inactive user', function () {
    User::factory()->inactive()->create([
        'username' => 'inactive.user',
        'password' => 'password',
    ]);

    Livewire::test(Login::class)
        ->set('username', 'inactive.user')
        ->set('password', 'password')
        ->call('authenticate')
        ->assertHasErrors(['username']);

    $this->assertGuest();
    $this->assertDatabaseCount('login_activities', 0);
});
