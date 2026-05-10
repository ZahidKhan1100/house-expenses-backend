# House Expenses API

## Filament staff admin (`/admin`)

Production URL: **`https://{your-api-host}/admin`** (derived from `APP_URL`). See **[DEPLOYMENT.md](./DEPLOYMENT.md)** for live env (`APP_URL`, `TRUSTED_PROXIES`, `SESSION_SECURE_COOKIE`, `ADMIN_*`), trusting proxies behind Railway/Cloudflare, and **`php artisan admin:create`** vs seeding.

Internal staff use a **separate** `admins` table and the **`admin`** session guard (not mobile `users`). The panel is Filament v4 with **Spatie Permission** and **Filament Shield**.

### First-time setup

1. Copy `.env.example` to `.env` and configure the database.
2. Set staff credentials for the seeder:

   - `ADMIN_EMAIL`
   - `ADMIN_PASSWORD`
   - Optional: `ADMIN_NAME`

3. Run migrations and seed:

   ```bash
   php artisan migrate --force
   php artisan db:seed --force
   ```

4. Open `/admin` and sign in with `ADMIN_EMAIL` / `ADMIN_PASSWORD`.

Shield permissions are generated from Filament entities; run `php artisan shield:generate` (or your project’s Shield workflow) after adding resources if you need fresh permission names.

### Roles

- **super-admin** — full access (also bypasses permission checks via `Gate::before` in `AuthServiceProvider`).
- **admin** / **editor** — grant resource access through Shield when editing roles under **System** (or assign roles to admins in **Admin** resource).

### Reset an admin password

Use **Admins** in the panel (if you can sign in), or from Tinker:

```bash
php artisan tinker
>>> \App\Models\Admin::where('email', 'you@example.com')->first()->update(['password' => 'new-secret']);
```

Use a hashed password in production (e.g. `Hash::make('...')`).

### Adding roles or permissions

1. Use Filament Shield’s **Roles** UI (guard: `admin`), or
2. Extend `Database\Seeders\AdminSeeder` for new base roles, then assign them on the **Admin** resource.
