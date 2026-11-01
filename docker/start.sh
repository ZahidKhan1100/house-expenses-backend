#!/bin/sh
# Container entrypoint for self-hosted deploys (Coolify / docker compose).
# ROLE=web (default) runs migrations and the HTTP server, ROLE=worker runs the queue,
# ROLE=scheduler runs `schedule:work`. Railway ignores this — it uses railway.json's startCommand.
set -e

cd /var/www/html

# Cache config/routes/views from the container's env. All settings are read through config(),
# so this is safe (env() calls outside config/ would return null once config is cached).
php artisan config:cache
php artisan route:cache
php artisan view:cache

case "${ROLE:-web}" in
  worker)
    exec php artisan queue:work --sleep=3 --tries=3 --max-time=3600
    ;;
  scheduler)
    exec php artisan schedule:work
    ;;
  web)
    php artisan migrate --force
    php artisan storage:link --force >/dev/null 2>&1 || true
    # Built-in server with several workers so requests don't queue behind each other.
    export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"
    exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
    ;;
  *)
    echo "Unknown ROLE: ${ROLE}" >&2
    exit 1
    ;;
esac
