#!/usr/bin/env bash
# Audit May splits against production API (api.habimate.com).
# Requires a Bearer token from a user IN the target house (not the solo demo house).
#
# Usage:
#   export HABIMATE_API_TOKEN='your-token-from-app-login'
#   ./scripts/audit-production-may.sh 2026-05
#
# On Railway (must run INSIDE the API container — not `railway run` on your Mac):
#   railway login && railway link   # pick API service
#   railway ssh -- php artisan cache:clear
#   railway ssh -- php artisan split:audit 2026-05 --users=3,4,5,6,7,8
# Optional: normalize included_mates order on existing rows
#   railway ssh -- php artisan split:normalize-included --house=2

set -euo pipefail
MONTH="${1:-2026-05}"
cd "$(dirname "$0")/.."

if [[ -z "${HABIMATE_API_TOKEN:-}" ]]; then
  echo "Set HABIMATE_API_TOKEN (login in app → copy token from secure storage or API login)." >&2
  exit 1
fi

php artisan split:audit "$MONTH" --from-api --users=3,4,5,6,7,8
