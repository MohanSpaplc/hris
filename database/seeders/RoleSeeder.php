<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Status;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private const PERMISSIONS = [
        'users.view' => 'View user list and details',
        'users.create' => 'Create users',
        'users.update' => 'Update users, change their role or status',
        'users.delete' => 'Deactivate / delete users',
        'roles.view' => 'View roles',
        'masters.view' => 'View countries, practice areas, clients, client managers, vendors',
        'masters.create' => 'Create master data',
        'masters.update' => 'Update master data',
        'masters.delete' => 'Delete master data',
        'jobs.view' => 'View jobs',
        'jobs.create' => 'Create jobs',
        'jobs.update' => 'Update jobs and change their status',
        'jobs.delete' => 'Delete jobs',
        'candidates.view' => 'View candidates and their documents',
        'candidates.create' => 'Add candidates and upload resumes',
        'candidates.update' => 'Update candidates, documents and their status',
        'candidates.delete' => 'Delete candidates',
    ];

    /**
     * Role code => [display name, permissions].
     *
     * @var array<string, array{0: string, 1: list<string>}>
     */
    private const ROLES = [
        Role::HR => ['HR', [
            'users.view', 'users.create', 'users.update', 'users.delete', 'roles.view',
            'masters.view', 'masters.create', 'masters.update', 'masters.delete',
            'jobs.view', 'jobs.create', 'jobs.update', 'jobs.delete',
            'candidates.view', 'candidates.create', 'candidates.update', 'candidates.delete',
        ]],
        Role::TAG_MANAGER => ['TAG Manager', ['users.view', 'roles.view', 'masters.view', 'masters.create', 'masters.update',
            'jobs.view', 'jobs.create', 'jobs.update', 'jobs.delete',
            'candidates.view', 'candidates.create', 'candidates.update', 'candidates.delete',
        ]],
        Role::TAG_TEAM => ['TAG Team', ['masters.view', 'jobs.view', 'candidates.view', 'candidates.create', 'candidates.update']],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::PERMISSIONS as $name => $description) {
            Permission::updateOrCreate(['name' => $name], ['description' => $description]);
        }

        $activeStatusId = Status::idFor(Status::MODULE_GENERAL, 'active');

        foreach (self::ROLES as $code => [$name, $permissions]) {
            $role = Role::updateOrCreate(['code' => $code], ['name' => $name, 'status_id' => $activeStatusId]);

            $role->permissions()->sync(Permission::whereIn('name', $permissions)->pluck('id'));
        }
    }
}
