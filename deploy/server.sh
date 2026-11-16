#!/usr/bin/env bash
# Uruchamiany NA SERWERZE (MyDevil) przez deploy.bat. Aktualizuje kod i aplikację.
set -euo pipefail

cd "$(dirname "$0")/.."

git pull --ff-only
/usr/local/bin/composer2 install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan optimize

echo "Deploy OK: $(git log -1 --format='%h %s')"
