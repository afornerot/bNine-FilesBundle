#!/usr/bin/env bash
# Bootstrap le sample en local (sans Docker, si PHP + composer dispos)
set -e
cd "$(dirname "$0")"

echo "==> composer install"
composer install --no-interaction --prefer-dist

echo "==> assets:install"
mkdir -p public
php bin/console assets:install public 2>/dev/null || true

echo "==> cache:clear"
php bin/console cache:clear --no-warmup 2>&1 | tail -3 || true

echo "==> Lancer avec : symfony serve  ou  php -S 0.0.0.0:9002 -t public"
