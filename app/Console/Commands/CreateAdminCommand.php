<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create
                            {--email= : Staff email address}
                            {--name= : Display name}
                            {--password= : Password (omit for hidden prompt)}
                            {--roles=super-admin : Comma-separated Spatie roles (guard: admin)}
                            {--force : Confirm updates when the admin already exists}';

    protected $description = 'Create or update a Filament staff admin in the admins table / admin guard';

    public function handle(): int
    {
        $email = (string) ($this->option('email') ?: $this->ask('Email address'));
        $name = (string) ($this->option('name') ?: $this->ask('Full name'));
        $password = (string) ($this->option('password') ?: $this->secret('Choose a password'));

        $validated = Validator::make(
            compact('email', 'name', 'password'),
            [
                'email' => ['required', 'email:rfc'],
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'min:10'],
            ],
        );

        if ($validated->fails()) {
            foreach ($validated->errors()->all() as $msg) {
                $this->error($msg);
            }

            return self::FAILURE;
        }

        $email = strtolower(trim($email));

        foreach (['super-admin', 'admin', 'editor'] as $roleName) {
            Role::findOrCreate($roleName, 'admin');
        }

        $existing = Admin::where('email', $email)->first();

        if ($existing && ! $this->option('force') && ! $this->confirm("Admin {$email} already exists. Update name and password?", false)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $admin = Admin::firstOrNew(['email' => $email]);
        $admin->name = trim($name);
        $admin->password = $password;
        $admin->save();

        $roles = array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) ($this->option('roles') ?: 'super-admin')),
        )));
        if ($roles === []) {
            $roles = ['super-admin'];
        }
        $admin->syncRoles($roles);

        $this->components->success(
            sprintf('Staff admin saved: %s (roles: %s)', $admin->email, implode(', ', $roles)),
        );
        $this->line(sprintf('Filament login: append <fg=cyan>/admin</> to your <fg=cyan>APP_URL</> (e.g. %s/admin)', rtrim((string) config('app.url'), '/')));

        return self::SUCCESS;
    }
}
