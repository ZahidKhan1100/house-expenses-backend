<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'admin';

        foreach (['super-admin', 'admin', 'editor'] as $roleName) {
            Role::findOrCreate($roleName, $guard);
        }

        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $email || ! $password) {
            if (isset($this->command)) {
                $this->command->warn('Skipping initial admin user: set ADMIN_EMAIL and ADMIN_PASSWORD in .env');
            }

            return;
        }

        $admin = Admin::firstOrNew(['email' => $email]);

        if (! $admin->exists) {
            $admin->fill([
                'name' => env('ADMIN_NAME', 'Super Admin'),
                'password' => $password,
            ]);
        }

        $admin->save();
        $admin->syncRoles(['super-admin']);
    }
}
