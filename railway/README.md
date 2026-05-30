# Railway — multiple services from one repo

| File | Service in Railway | Role |
|------|-------------------|------|
| `/railway.json` | **API** (default) | Web: `php artisan serve` + `migrate --force` on deploy |
| `/railway.scheduler.json` | **Scheduler** (second service) | Background: `php artisan schedule:work` (wall snippet cleanup, backups) |

## Scheduler service setup

1. In the same Railway project: **+ New** → connect **this repo** again.
2. Name it e.g. `habimate-scheduler`.
3. **Settings → Config-as-code** → set **Config file path** to:
   ```
   /railway.scheduler.json
   ```
4. **Variables**: copy or reference the same env as the API (`DATABASE_URL`, `APP_KEY`, Cloudinary, etc.). No public domain needed.
5. Deploy. Check logs for `schedule:work` / daily tasks.

Do **not** use the default `/railway.json` on the scheduler service — that would start another HTTP server and run migrations again.

Alternative start command (if `schedule:work` is unavailable):  
`chmod +x ./railway/run-cron.sh && sh ./railway/run-cron.sh`
