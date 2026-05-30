#!/bin/bash
# Railway scheduler worker: runs Laravel schedule:run every ~60s.
# Deploy as a second service (same repo + env as API) with start command:
#   chmod +x ./railway/run-cron.sh && sh ./railway/run-cron.sh
# Or use: php artisan schedule:work

set -e

while true; do
  php artisan schedule:run --verbose --no-interaction || true
  sleep 60
done
