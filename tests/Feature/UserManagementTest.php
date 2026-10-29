<?php

use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

function roleId(string $code): int
{
    return Role::where('code', $code)->value('id');
}

test('HR can create a user and a set-password link is emailed', function () {
    Notification::fake();
    $hr = User::factory()->role(Role::HR)->create();
    Sanctum::actingAs($hr);

    $this->postJson('/api/v1/users', [
        'first_name' => 'Ravi',
        'last_name' => 'Kumar',
        'email' => 'ravi@example.com',
        'role_id' => roleId(Role::TAG_TEAM),
    ])->assertCreated()->assertJsonPath('data.role.code', Role::TAG_TEAM);

    $user = User::where('email', 'ravi@example.com')->firstOrFail();
    expect($user->created_by)->toBe($hr->id);
    Notification::assertSentTo($user, ResetPassword::class);
});

test('user creation ignores status_id and password from input', function () {
    Notification::fake();
    Sanctum::actingAs(User::factory()->role(Role::HR)->create());

    $this->postJson('/api/v1/users', [
        'first_name' => 'Asha',
        'email' => 'asha@example.com',
        'role_id' => roleId(Role::TAG_TEAM),
        'status_id' => Status::idFor('general', 'inactive'),
        'password' => 'password',
    ])->assertCreated();

    $user = User::where('email', 'asha@example.com')->firstOrFail();
    expect($user->isActive())->toBeTrue();
    $this->postJson('/api/v1/auth/login', ['email' => 'asha@example.com', 'password' => 'password'])->assertUnprocessable();
});

test('TAG Manager and TAG Team cannot create users', function (string $roleCode) {
    Sanctum::actingAs(User::factory()->role($roleCode)->create());

    $this->postJson('/api/v1/users', [
        'first_name' => 'X',
        'email' => 'x@example.com',
        'role_id' => roleId(Role::HR),
    ])->assertForbidden();
})->with([Role::TAG_MANAGER, Role::TAG_TEAM]);

test('TAG Manager can list users but TAG Team cannot', function () {
    Sanctum::actingAs(User::factory()->role(Role::TAG_MANAGER)->create());
    $this->getJson('/api/v1/users')->assertOk()->assertJsonStructure(['data', 'meta', 'links']);

    Sanctum::actingAs(User::factory()->role(Role::TAG_TEAM)->create());
    $this->getJson('/api/v1/users')->assertForbidden();
});

test('TAG Team can view only their own profile', function () {
    $member = User::factory()->role(Role::TAG_TEAM)->create();
    $other = User::factory()->create();
    Sanctum::actingAs($member);

    $this->getJson("/api/v1/users/{$member->id}")->assertOk();
    $this->getJson("/api/v1/users/{$other->id}")->assertForbidden();
});

test('HR cannot change their own role or status', function () {
    $hr = User::factory()->role(Role::HR)->create();
    Sanctum::actingAs($hr);

    $this->putJson("/api/v1/users/{$hr->id}", ['role_id' => roleId(Role::TAG_TEAM)])
        ->assertJsonValidationErrors('role_id');
});

test('changing a role revokes the user tokens', function () {
    $member = User::factory()->role(Role::TAG_TEAM)->create();
    $member->createToken('api');
    Sanctum::actingAs(User::factory()->role(Role::HR)->create());

    $this->putJson("/api/v1/users/{$member->id}", ['role_id' => roleId(Role::TAG_MANAGER)])
        ->assertOk()
        ->assertJsonPath('data.role.code', Role::TAG_MANAGER);

    expect($member->tokens()->count())->toBe(0);
});

test('status must belong to the general module', function () {
    $member = User::factory()->create();
    Sanctum::actingAs(User::factory()->role(Role::HR)->create());

    $this->putJson("/api/v1/users/{$member->id}", ['status_id' => Status::idFor('offer', 'accepted')])
        ->assertJsonValidationErrors('status_id');
});

test('HR can delete another user but not themselves', function () {
    $hr = User::factory()->role(Role::HR)->create();
    $member = User::factory()->create();
    Sanctum::actingAs($hr);

    $this->deleteJson("/api/v1/users/{$hr->id}")->assertForbidden();
    $this->deleteJson("/api/v1/users/{$member->id}")->assertOk();

    expect($member->fresh()->trashed())->toBeTrue();
});

test('API responses carry security headers', function () {
    $this->getJson('/api/v1/auth/me')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY');
});
