<?php

use App\Models\Client;
use App\Models\ClientManager;
use App\Models\Country;
use App\Models\PracticeArea;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\HrAdminSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

function actingAsRole(string $roleCode): User
{
    $user = User::factory()->role($roleCode)->create();
    Sanctum::actingAs($user);

    return $user;
}

test('seeder creates 5 records in every master table', function () {
    config(['hris.admin_email' => 'hr@example.com', 'hris.admin_password' => 'Strong#Pass123']);
    $this->seed([HrAdminSeeder::class, MasterDataSeeder::class]);

    expect(Country::count())->toBe(5)
        ->and(PracticeArea::count())->toBe(5)
        ->and(Client::count())->toBe(5)
        ->and(ClientManager::count())->toBe(5)
        ->and(Vendor::count())->toBe(5);

    // Running again must not duplicate anything.
    $this->seed(MasterDataSeeder::class);
    expect(Client::count())->toBe(5);
});

test('every role can list masters', function (string $roleCode, string $uri) {
    actingAsRole($roleCode);

    $this->getJson("/api/v1/{$uri}")->assertOk()->assertJsonStructure(['data', 'meta', 'links']);
})->with([Role::HR, Role::TAG_MANAGER, Role::TAG_TEAM])
    ->with(['countries', 'practice-areas', 'clients', 'client-managers', 'vendors']);

test('HR can create a country and code is upper-cased', function () {
    actingAsRole(Role::HR);

    $this->postJson('/api/v1/countries', [
        'name' => 'Germany', 'code' => 'de', 'currency_code' => 'eur', 'dial_code' => '+49', 'timezone' => 'Europe/Berlin',
    ])->assertCreated()
        ->assertJsonPath('data.code', 'DE')
        ->assertJsonPath('data.currency_code', 'EUR')
        ->assertJsonPath('data.status.code', 'active');
});

test('country validation rejects duplicates and bad values', function () {
    actingAsRole(Role::HR);
    Country::factory()->create(['name' => 'India', 'code' => 'IN']);

    $this->postJson('/api/v1/countries', ['name' => 'India', 'code' => 'IN', 'dial_code' => '91', 'timezone' => 'Mars/Base'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'code', 'dial_code', 'timezone']);
});

test('country used by a client cannot be deleted', function () {
    actingAsRole(Role::HR);
    $client = Client::factory()->create();

    $this->deleteJson("/api/v1/countries/{$client->country_id}")->assertConflict();
    $this->assertDatabaseHas('countries', ['id' => $client->country_id]);
});

test('client is created with the logged-in user as creator and can be filtered by country', function () {
    $hr = actingAsRole(Role::HR);
    $country = Country::factory()->create();
    Client::factory()->count(2)->create();

    $this->postJson('/api/v1/clients', [
        'country_id' => $country->id,
        'name' => 'Contoso Pvt Ltd',
        'email' => 'Info@Contoso.Example',
        'created_by' => 999,
    ])->assertCreated()
        ->assertJsonPath('data.email', 'info@contoso.example')
        ->assertJsonPath('data.created_by.id', $hr->id);

    $this->getJson("/api/v1/clients?country_id={$country->id}")->assertOk()->assertJsonCount(1, 'data');
});

test('client cannot use an inactive country', function () {
    actingAsRole(Role::HR);
    $country = Country::factory()->create(['status_id' => Status::idFor('general', 'inactive')]);

    $this->postJson('/api/v1/clients', ['country_id' => $country->id, 'name' => 'X'])
        ->assertJsonValidationErrors('country_id');
});

test('deleting a client soft deletes its managers', function () {
    actingAsRole(Role::HR);
    $manager = ClientManager::factory()->create();

    $this->deleteJson("/api/v1/clients/{$manager->client_id}")->assertOk();

    expect(Client::withTrashed()->find($manager->client_id)->trashed())->toBeTrue()
        ->and($manager->fresh()->trashed())->toBeTrue();
});

test('client managers can be filtered by client', function () {
    actingAsRole(Role::TAG_TEAM);
    $manager = ClientManager::factory()->create();
    ClientManager::factory()->create();

    $this->getJson("/api/v1/client-managers?client_id={$manager->client_id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.client.id', $manager->client_id);
});

test('partial update works and status must be a general status', function () {
    actingAsRole(Role::TAG_MANAGER);
    $vendor = Vendor::factory()->create(['name' => 'Old Name']);

    $this->putJson("/api/v1/vendors/{$vendor->id}", ['contact_person' => 'New Person'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Old Name')
        ->assertJsonPath('data.contact_person', 'New Person');

    $this->putJson("/api/v1/vendors/{$vendor->id}", ['status_id' => Status::idFor('offer', 'accepted')])
        ->assertJsonValidationErrors('status_id');

    $this->putJson("/api/v1/vendors/{$vendor->id}", ['status_id' => Status::idFor('general', 'inactive')])
        ->assertOk()
        ->assertJsonPath('data.status.code', 'inactive');
});

test('practice area code is unique and head must be an active user', function () {
    actingAsRole(Role::HR);
    PracticeArea::factory()->create(['code' => 'JAVA']);
    $inactiveUser = User::factory()->inactive()->create();

    $this->postJson('/api/v1/practice-areas', ['name' => 'Java', 'code' => 'java', 'head_id' => $inactiveUser->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code', 'head_id']);
});

test('TAG Team cannot create, update or delete masters', function () {
    actingAsRole(Role::TAG_TEAM);
    $vendor = Vendor::factory()->create();

    $this->postJson('/api/v1/vendors', ['name' => 'X'])->assertForbidden();
    $this->putJson("/api/v1/vendors/{$vendor->id}", ['name' => 'Y'])->assertForbidden();
    $this->deleteJson("/api/v1/vendors/{$vendor->id}")->assertForbidden();
});

test('TAG Manager can create and update but not delete masters', function () {
    actingAsRole(Role::TAG_MANAGER);
    $vendor = Vendor::factory()->create();

    $this->postJson('/api/v1/vendors', ['name' => 'New Vendor'])->assertCreated();
    $this->deleteJson("/api/v1/vendors/{$vendor->id}")->assertForbidden();
});

test('statuses endpoint returns general statuses', function () {
    actingAsRole(Role::TAG_TEAM);

    $this->getJson('/api/v1/statuses?module=general')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.code', 'active');
});

test('lists show the newest record first, or A-Z with sort=name', function () {
    actingAsRole(Role::HR);
    Country::factory()->create(['name' => 'Austria', 'code' => 'AT']);
    Country::factory()->create(['name' => 'Zambia', 'code' => 'ZM']);
    Country::factory()->create(['name' => 'Malta', 'code' => 'MT']);

    $this->getJson('/api/v1/countries')->assertOk()->assertJsonPath('data.0.name', 'Malta');
    $this->getJson('/api/v1/countries?sort=name')->assertOk()->assertJsonPath('data.0.name', 'Austria');
    $this->getJson('/api/v1/countries?sort=random')->assertJsonValidationErrors('sort');
});

test('client logo can be uploaded, replaced and removed', function () {
    Storage::fake('public');
    actingAsRole(Role::HR);
    $client = Client::factory()->create();

    $first = $this->post("/api/v1/clients/{$client->id}/logo", ['logo' => UploadedFile::fake()->image('logo.png', 200, 200)])
        ->assertOk()
        ->assertJsonPath('data.id', $client->id);
    $firstPath = $client->fresh()->logo_path;
    expect($first->json('data.logo_url'))->toContain('/storage/client-logos/');
    Storage::disk('public')->assertExists($firstPath);

    // Replacing deletes the old file.
    $this->post("/api/v1/clients/{$client->id}/logo", ['logo' => UploadedFile::fake()->image('new.jpg', 100, 100)])->assertOk();
    Storage::disk('public')->assertMissing($firstPath);

    $secondPath = $client->fresh()->logo_path;
    $this->deleteJson("/api/v1/clients/{$client->id}/logo")->assertOk()->assertJsonPath('data.logo_url', null);
    Storage::disk('public')->assertMissing($secondPath);
});

test('client logo rejects svg, non-images and large files', function () {
    Storage::fake('public');
    actingAsRole(Role::HR);
    $client = Client::factory()->create();

    foreach ([
        UploadedFile::fake()->create('logo.svg', 5, 'image/svg+xml'),
        UploadedFile::fake()->create('logo.pdf', 5, 'application/pdf'),
        UploadedFile::fake()->image('big.png')->size(2048),
    ] as $file) {
        $this->post("/api/v1/clients/{$client->id}/logo", ['logo' => $file], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('logo');
    }
});

test('TAG Team cannot upload a client logo', function () {
    Storage::fake('public');
    actingAsRole(Role::TAG_TEAM);
    $client = Client::factory()->create();

    $this->post("/api/v1/clients/{$client->id}/logo", ['logo' => UploadedFile::fake()->image('logo.png')])
        ->assertForbidden();
});
