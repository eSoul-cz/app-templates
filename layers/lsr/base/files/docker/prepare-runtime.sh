#!/usr/bin/env sh

set -eu

mkdir -p ./logs/tracy ./temp/cache ./temp/latte ./build ./upload /var/www/public/build

# Console commands bootstrap the DI container first, so stale generated files must go first.
rm -f ./temp/config.cache \
  ./temp/*.php ./temp/*.php.lock ./temp/*.php.meta

# Never expose the application root or the private Vite manifest as a static directory.
if [ ! -L /var/www/public/build/assets ]; then
  ln -s /app/build/assets /var/www/public/build/assets
fi

php bin/console orm:cache:clean
php bin/console latte:cache:clean
php bin/console routes:cache:clean
php bin/console routes:cache:compile

# Create/update the database schema (config/migrations).
php bin/console app:install
