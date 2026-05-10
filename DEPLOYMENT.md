# Deploying house-expenses-backend (live)

This API already includes a **Filament v4 staff panel** mounted at **`/admin`** (`App\Providers\Filament\AdminPanelProvider`). Operators sign in via the **`admins`** table / **`admin`** session guard—not the mobile `users` table.

## URLs

- Panel: **`{APP_URL}/admin`** — e.g. `https://api.habimate.com/admin`
- REST API stays under **`/api/v1/...`** (unchanged).

## One-time setup on the server

1. **Environment**
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - `APP_URL` = public HTTPS URL of **this Laravel app** (must match TLS host for sessions and Filament).
   - `SESSION_DRIVER=database` (or Redis) — already supported.
   - `SESSION_SECURE_COOKIE=true` when HTTPS-only.
   - **Trust proxies** (Railway / Cloudflare / load balancers):
     - Omit `TRUSTED_PROXIES` → in **production** (`APP_ENV=production`), forwarded headers from upstream proxies are trusted (same behaviour as `*`).
     - Non-production stages (e.g. `APP_ENV=staging` on Railway) **do not** auto-trust — set **`TRUSTED_PROXIES=*`** explicitly if you use a managed proxy.
     - Or set **`TRUSTED_PROXIES=*`** explicitly.
     - Set **`TRUSTED_PROXIES=false`** when running without a proxy (e.g. local `php artisan serve`).
     - Without trust in production load-balanced setups, HTTPS detection, redirects, cookies, and **`ADMIN_ALLOWED_IPS`** can misbehave.

2. **Database**
   - Run migrations (release phase or deploy hook):  
     `php artisan migrate --force`

3. **First staff account** (pick one)
   - **Env + seeder** (good for first deploy): set `ADMIN_NAME`, **`ADMIN_EMAIL`**, **`ADMIN_PASSWORD`** then run:  
     `php artisan db:seed --force`  
     *(Creates nothing when email/password are blank.)*
   - **Artisan command** (avoids committing secrets to `.env` long-term):  
     `php artisan admin:create`  
     or non-interactive:  
     `php artisan admin:create --email=you@org.com --name="Jane" --password='minimum-10-chars' --roles=super-admin --force`

4. **Roles & Shield**
   - `AdminSeeder` creates Spatie roles: `super-admin`, `admin`, `editor`.
   - `super-admin` bypasses granular checks via `AuthServiceProvider` `Gate::before`.
   - After adding Filament resources, refresh Shield permissions with your workflow (e.g. `php artisan shield:generate`) when you rely on granular policies.

## Hardening (recommended)

| Control | Env |
|--------|-----|
| IP allow-list for `/admin` | **`ADMIN_ALLOWED_IPS`** comma-separated client IPs (`RestrictAdminPanelByIp`; requires proxies trusted correctly). |
| MFA for panel | **`FILAMENT_ADMIN_MFA_REQUIRED=true`** (Filament App / TOTP, recoverable). |

Also restrict the hostname at firewall / CDN / VPN—middleware is defence in depth.

## Railway / Docker notes

- **Release command**: run `migrate` (and optional one-time seed) separately from the long-running web process—do **not** run `db:seed` on every container boot.
- **Web root** must be **`public/`**. Run `php artisan storage:link` if you serve user uploads from `public/storage`.

## Health

- Laravel health route: **`/up`**
