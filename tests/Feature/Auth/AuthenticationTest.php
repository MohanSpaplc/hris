<?php

use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

const STRONG_PASSWORD = 'N3w-Secure#Pass';

test('user can log in with valid credentials and receives a token', function () {
    $user = User::factory()->role(Role::HR)->create();

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
        ->assertOk()
        ->assertJsonStructure(['token', 'token_type', 'expires_at', 'user' => ['id', 'email', 'role'], 'permissions'])
        ->assertJsonPath('user.role.code', Role::HR);

    expect($user->fresh()->last_login_at)->not->toBeNull();
});

test('login fails with the same message for wrong password and unknown email', function () {
    $user = User::factory()->create();

    $wrongPassword = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong'])
        ->assertUnprocessable();
    $unknownEmail = $this->postJson('/api/v1/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])
        ->assertUnprocessable();

    expect($wrongPassword->json('errors.email'))->toBe($unknownEmail->json('errors.email'));
});

test('inactive user cannot log in', function () {
    $user = User::factory()->inactive()->create();

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
        ->assertForbidden();
});

test('login is rate limited after 5 attempts', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong']);
    }

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
        ->assertTooManyRequests();
});

test('protected routes require a token', function () {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

test('token of a deactivated user stops working', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api')->plainTextToken;
    $user->forceFill(['status_id' => Status::idFor('general', 'inactive')])->save();

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertForbidden();
    expect($user->tokens()->count())->toBe(0);
});

test('logout revokes the current token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

    expect($user->tokens()->count())->toBe(0);
});

test('forgot password returns the same response for known and unknown emails', function () {
    Notification::fake();
    $user = User::factory()->create();

    $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
    $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk();

    expect($known->json())->toBe($unknown->json());
    Notification::assertSentTo($user, ResetPassword::class);
});

test('password can be reset with a valid token and all tokens are revoked', function () {
    $user = User::factory()->create();
    $user->createToken('old-device');
    $token = Password::createToken($user);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => STRONG_PASSWORD,
        'password_confirmation' => STRONG_PASSWORD,
    ])->assertOk();

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => STRONG_PASSWORD])->assertOk();
    expect($user->tokens()->where('name', 'old-device')->exists())->toBeFalse();
});

test('password reset rejects an invalid token and a weak password', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => 'invalid',
        'email' => $user->email,
        'password' => STRONG_PASSWORD,
        'password_confirmation' => STRONG_PASSWORD,
    ])->assertUnprocessable()->assertJsonValidationErrors('email');

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => Password::createToken($user),
        'email' => $user->email,
        'password' => 'weak',
        'password_confirmation' => 'weak',
    ])->assertUnprocessable()->assertJsonValidationErrors('password');
});

test('user can change password with the current password', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/auth/change-password', [
        'current_password' => 'wrong',
        'password' => STRONG_PASSWORD,
        'password_confirmation' => STRONG_PASSWORD,
    ])->assertJsonValidationErrors('current_password');

    $this->withToken($token)->postJson('/api/v1/auth/change-password', [
        'current_password' => 'password',
        'password' => STRONG_PASSWORD,
        'password_confirmation' => STRONG_PASSWORD,
    ])->assertOk();
});
