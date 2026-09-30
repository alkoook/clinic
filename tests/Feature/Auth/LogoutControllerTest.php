<?php

use App\Models\LoginActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('logs the user out and closes the current login activity', function () {
    $user = User::factory()->create();
    $activity = LoginActivity::factory()->for($user)->create([
        'session_identifier' => 'current-session',
        'logout_at' => null,
    ]);

    $response = $this
        ->actingAs($user)
        ->withSession(['login_activity_id' => $activity->id])
        ->post(route('logout'));

    $response
        ->assertRedirectToRoute('login')
        ->assertSessionHas('success', 'تم تسجيل الخروج بأمان.');
    $this->assertGuest();
    expect($activity->refresh()->logout_at)->not->toBeNull();
});

it('logs an inactive user out before a protected page is rendered', function () {
    $user = User::factory()->inactive()->create();
    $activity = LoginActivity::factory()->for($user)->create(['logout_at' => null]);

    $response = $this
        ->actingAs($user)
        ->withSession(['login_activity_id' => $activity->id])
        ->get(route('dashboard'));

    $response
        ->assertRedirect('/login')
        ->assertSessionHas('error', 'تم تعطيل هذا الحساب. راجع إدارة العيادة.');
    $this->assertGuest();
    expect($activity->refresh()->logout_at)->not->toBeNull();
});
