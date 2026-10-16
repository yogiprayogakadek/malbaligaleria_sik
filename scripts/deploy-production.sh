#!/usr/bin/env bash

set -Eeuo pipefail

APP_DIR="${APP_DIR:-/var/www/sik}"
PHP_BINARY="${PHP_BINARY:-php}"
COMPOSER_BINARY="${COMPOSER_BINARY:-composer}"

cd "$APP_DIR"

"$COMPOSER_BINARY" install --no-dev --prefer-dist --no-interaction --optimize-autoloader

if [[ "${SKIP_ASSET_BUILD:-0}" != "1" ]]; then
  npm ci
  npm run build
fi

"$PHP_BINARY" artisan app:preflight --before-migrate

maintenance_enabled=0
restore_service() {
  if [[ "$maintenance_enabled" == "1" ]]; then
    "$PHP_BINARY" artisan up || true
  fi
}
trap restore_service EXIT

"$PHP_BINARY" artisan down --retry=60
maintenance_enabled=1

"$PHP_BINARY" artisan optimize:clear
"$PHP_BINARY" artisan migrate --force
"$PHP_BINARY" artisan optimize
"$PHP_BINARY" artisan app:preflight
"$PHP_BINARY" artisan queue:restart

"$PHP_BINARY" artisan up
maintenance_enabled=0

echo "Deployment selesai. Pastikan Supervisor menjalankan queue, scheduler, dan Reverb."
