<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class HrAdminSeeder extends Seeder
{
    /**
     * Create the first HR user. Credentials come from .env, never from source code.
     * If HR_ADMIN_PASSWORD is empty a random one is generated and printed once.
     */
    public function run(): void
    {
        $email = config('hris.admin_email');

        if (! $email || User::withTrashed()->where('email', $email)->exists()) {
            return;
        }

        $password = config('hris.admin_password') ?: Str::password(16);

        $user = new User([
            'first_name' => 'HR',
            'last_name' => 'Admin',
            'email' => $email,
            'password' => $password,
        ]);
        $user->role_id = Role::where('code', Role::HR)->value('id');
        $user->status_id = Status::idFor(Status::MODULE_GENERAL, 'active');
        $user->email_verified_at = now();
        $user->password_changed_at = now();
        $user->save();

        if (! config('hris.admin_password')) {
            $this->command?->warn("HR admin created: {$email} / {$password}  (change it after first login)");
        }
    }
}
